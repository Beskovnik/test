<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Settings;
use App\Media;

// Ensure JSON
header('Content-Type: application/json');

// Check Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Check Auth
$user = Auth::requireLogin();

// Check CSRF
verify_csrf();

// Check Permissions
if (!is_writable(__DIR__ . '/../uploads')) {
    Response::error('Server configuration error: Uploads not writable', 'PERM_ERROR', 500);
}

$uploadDir = __DIR__ . '/../uploads';
$chunkDir = $uploadDir . '/chunks';
if (!is_dir($chunkDir)) {
    mkdir($chunkDir, 0777, true);
}

// Check if this is a chunked upload
$uploadId = $_POST['upload_id'] ?? null;
$chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : null;
$totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : null;

// File Data
$file = $_FILES['file_data'] ?? null; // Single chunk/file
if (!$file) {
    // Legacy support (non-chunked array upload)
    $files = $_FILES['files'] ?? null;
    if ($files) {
        // Fallback to old logic or reject?
        // Since we updated frontend to always use chunking for single/multi files loop,
        // we can probably assume chunking parameters are present or at least single-file via 'file_data'.
        // But for safety, if someone hits API directly:
        Response::error('No file data received', 'NO_FILES');
    } else {
        Response::error('No file data received', 'NO_FILES');
    }
}

// Handle Chunk
if ($uploadId && $chunkIndex !== null && $totalChunks !== null) {
    $tempPath = $chunkDir . '/' . $uploadId;

    // Append chunk
    $chunkData = file_get_contents($file['tmp_name']);
    if ($chunkData === false) {
        Response::error('Failed to read chunk', 'CHUNK_READ_ERROR');
    }

    // Lock file to avoid race conditions if concurrent chunks (frontend sends sequentially but safer)
    $fp = fopen($tempPath, 'a');
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $chunkData);
        flock($fp, LOCK_UN);
    }
    fclose($fp);

    // If not last chunk, return success
    if ($chunkIndex < $totalChunks - 1) {
        Response::json(['success' => true, 'chunk' => $chunkIndex]);
        exit;
    }

    // Last chunk received, proceed to process complete file
    // Use the assembled temp file
    $finalPath = $tempPath;
    $originalName = $_POST['file_name'] ?? 'unknown';
    $mimeType = $_POST['file_type'] ?? 'application/octet-stream';
    $fileSize = (int)($_POST['file_size'] ?? filesize($finalPath));

    processFile($finalPath, $originalName, $mimeType, $fileSize, $user);

    // Cleanup
    if (file_exists($finalPath)) {
        unlink($finalPath);
    }

} else {
    // Non-chunked single file upload (fallback)
    // Assuming 'file_data' is the full file
    processFile($file['tmp_name'], $file['name'], $file['type'], $file['size'], $user);
}

function processFile($sourcePath, $originalName, $mimeType, $fileSize, $user) {
    global $pdo;

    // Config
    $maxImageGb = (float)Settings::get($pdo, 'max_image_gb', '5.0');
    $maxVideoGb = (float)Settings::get($pdo, 'max_video_gb', '5.0');

    $maxImageBytes = $maxImageGb * 1024 * 1024 * 1024;
    $maxVideoBytes = $maxVideoGb * 1024 * 1024 * 1024;

    $allowedImages = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $allowedVideos = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];
    $blockedExts = ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'exe', 'sh'];

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (in_array($ext, $blockedExts, true)) {
        Response::error('Blocked file type', 'BLOCKED_TYPE');
    }

    // Verify MIME type server-side
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($sourcePath);

    $isImage = str_starts_with($realMime, 'image/');
    $isVideo = str_starts_with($realMime, 'video/');

    if ($isImage) {
        if (!in_array($realMime, $allowedImages, true)) {
            Response::error('Invalid image format: ' . $realMime, 'INVALID_FORMAT');
        }
        if ($fileSize > $maxImageBytes) {
            Response::error('Image too large', 'TOO_LARGE');
        }
    } elseif ($isVideo) {
        if (!in_array($realMime, $allowedVideos, true)) {
            Response::error('Invalid video format: ' . $realMime, 'INVALID_FORMAT');
        }
        if ($fileSize > $maxVideoBytes) {
            Response::error('Video too large', 'TOO_LARGE');
        }
    } else {
        Response::error('Unsupported media type: ' . $realMime, 'UNSUPPORTED_TYPE');
    }

    // Destinations
    $random = bin2hex(random_bytes(16));
    $filename = $random . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads';
    $targetOriginal = $uploadDir . '/original/' . $filename;

    // Move (or rename if from chunks)
    // If it's a chunk assembly, $sourcePath is the temp file we created.
    // If it's standard upload, $sourcePath is /tmp/php...
    // In both cases we can rename/move.
    if (!rename($sourcePath, $targetOriginal)) {
        // Try copy/unlink if rename fails (cross-device)
        if (!copy($sourcePath, $targetOriginal)) {
            Response::error('Failed to save file', 'SAVE_ERROR');
        }
    }

    $targetThumb = $uploadDir . '/thumbs/' . $random . '.webp';
    $targetPreview = $uploadDir . '/preview/' . $random . '.webp';

    $width = null;
    $height = null;
    $thumbSuccess = false;
    $previewSuccess = false;

    // Paths relative to root for DB
    $dbOriginal = 'uploads/original/' . $filename;
    $dbThumb = 'uploads/thumbs/' . $random . '.webp';
    $dbPreview = 'uploads/preview/' . $random . '.webp';

    if ($isImage) {
        $info = getimagesize($targetOriginal);
        if ($info) {
            $width = $info[0];
            $height = $info[1];
        }
        // Generate Thumb (420px)
        $thumbSuccess = Media::generateResized($targetOriginal, $targetThumb, 420, 420);
        // Generate Preview (1600px)
        $previewSuccess = Media::generateResized($targetOriginal, $targetPreview, 1600, 1600);
    } else {
        // Video
        $dbThumb = 'uploads/thumbs/' . $random . '.jpg';
        $targetThumb = $uploadDir . '/thumbs/' . $random . '.jpg';
        $dbPreview = 'uploads/preview/' . $random . '.jpg';
        $targetPreview = $uploadDir . '/preview/' . $random . '.jpg';

        $thumbSuccess = Media::generateVideoThumb($targetOriginal, $targetThumb);
        // For video preview, we use the same poster but maybe higher res if we had better logic
        if ($thumbSuccess) {
            copy($targetThumb, $targetPreview);
            $previewSuccess = true;
        }
    }

    // Fallbacks
    if (!$thumbSuccess) {
        if ($isImage) $dbThumb = $dbOriginal;
        else $dbThumb = 'assets/img/video_placeholder.jpg';
    }
    if (!$previewSuccess) {
        if ($isImage) $dbPreview = $dbOriginal;
        else $dbPreview = $dbThumb;
    }

    // Save to DB
    $shareToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, created_at, visibility, share_token, type, file_path, mime, size_bytes, width, height, thumb_path, preview_path)
        VALUES (:uid, :title, :created, "public", :token, :type, :path, :mime, :size, :w, :h, :thumb, :preview)');

    $stmt->execute([
        ':uid' => $user['id'],
        ':title' => pathinfo($originalName, PATHINFO_FILENAME),
        ':created' => time(),
        ':token' => $shareToken,
        ':type' => $isImage ? 'image' : 'video',
        ':path' => $dbOriginal,
        ':mime' => $realMime,
        ':size' => $fileSize,
        ':w' => $width,
        ':h' => $height,
        ':thumb' => $dbThumb,
        ':preview' => $dbPreview
    ]);

    Response::json(['success' => true, 'post_id' => (int)$pdo->lastInsertId()]);
}
