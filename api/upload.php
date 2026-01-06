<?php
// Start output buffering immediately to capture any noise (warnings, notices)
ob_start();

require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Settings;
use App\Media;

// Disable display_errors to prevent breaking JSON structure
ini_set('display_errors', '0');

// Ensure JSON header
header('Content-Type: application/json');

// Helper to send JSON with debug info
function send_json_response($data, $status = 200) {
    global $debug_log;

    // Capture any output that happened before this point
    $buffered_output = ob_get_clean();

    if (!empty($buffered_output) || !empty($debug_log)) {
        $data['debug_output'] = $buffered_output;
        $data['debug_log'] = $debug_log ?? [];
        if (!empty($buffered_output)) {
            error_log("API Buffered Output: " . $buffered_output);
        }
    }

    Response::json($data, $status);
}

// Helper to send Error
function send_error_response($message, $code = 'ERROR', $status = 400) {
    send_json_response([
        'success' => false,
        'error' => $message,
        'code' => $code
    ], $status);
}

try {
    // Check Method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_error_response('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }

    // Check Auth
    $user = Auth::requireLogin();

    // Check CSRF
    verify_csrf();

    // Check Permissions
    if (!is_writable(__DIR__ . '/../uploads')) {
        send_error_response('Server configuration error: Uploads not writable', 'PERM_ERROR', 500);
    }

    $uploadDir = __DIR__ . '/../uploads';
    $chunkDir = $uploadDir . '/chunks';

    // Ensure directories exist
    if (!is_dir($chunkDir)) {
        if (!mkdir($chunkDir, 0777, true)) {
            $lastErr = error_get_last();
            send_error_response('Failed to create chunk directory: ' . ($lastErr['message'] ?? ''), 'DIR_CREATE_ERROR', 500);
        }
    }

    if (!is_writable($chunkDir)) {
         send_error_response('Chunk directory not writable', 'PERM_ERROR', 500);
    }

    // Check if this is a chunked upload
    $uploadId = $_POST['upload_id'] ?? null;
    $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : null;
    $totalChunks = isset($_POST['total_chunks']) ? (int)$_POST['total_chunks'] : null;

    // File Data
    $file = $_FILES['file_data'] ?? null; // Single chunk/file
    if (!$file) {
        // Fallback for full uploads
        $files = $_FILES['files'] ?? null;
        if ($files) {
            send_error_response('Legacy array upload not supported in this endpoint', 'NO_FILES');
        } else {
            send_error_response('No file data received', 'NO_FILES');
        }
    }

    // Handle Chunk
    if ($uploadId && $chunkIndex !== null && $totalChunks !== null) {
        // Sanitize uploadId
        $uploadId = preg_replace('/[^a-zA-Z0-9]/', '', $uploadId);
        if (empty($uploadId)) {
             send_error_response('Invalid Upload ID', 'INVALID_ID');
        }

        $tempPath = $chunkDir . '/' . $uploadId;

        // Append chunk
        $chunkData = file_get_contents($file['tmp_name']);
        if ($chunkData === false) {
            send_error_response('Failed to read chunk data', 'CHUNK_READ_ERROR');
        }

        // Lock file to avoid race conditions
        $fp = fopen($tempPath, 'a');
        if (!$fp) {
             send_error_response('Failed to open temp file', 'TEMP_FILE_ERROR');
        }

        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $chunkData);
            flock($fp, LOCK_UN);
        } else {
            fclose($fp);
            send_error_response('Failed to lock file', 'LOCK_ERROR');
        }
        fclose($fp);

        // If not last chunk, return success
        if ($chunkIndex < $totalChunks - 1) {
            send_json_response(['success' => true, 'chunk' => $chunkIndex]);
            exit;
        }

        // Last chunk received, proceed to process complete file
        $finalPath = $tempPath;
        $originalName = $_POST['file_name'] ?? 'unknown';
        // $mimeType from client is unreliable, we check server side later
        $fileSize = (int)($_POST['file_size'] ?? filesize($finalPath));

        processFile($finalPath, $originalName, $fileSize, $user);

        // Cleanup
        if (file_exists($finalPath)) {
            unlink($finalPath);
        }

    } else {
        // Non-chunked single file upload (fallback)
        processFile($file['tmp_name'], $file['name'], $file['size'], $user);
    }

} catch (Throwable $e) {
    send_error_response('Exception: ' . $e->getMessage(), 'EXCEPTION', 500);
}

// Robust MIME detection function
function get_real_mime_type($path) {
    // 1. Primary: FileInfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);

    // If it looks valid, return it
    if ($mime && $mime !== 'application/octet-stream' && $mime !== 'application/x-empty') {
        return $mime;
    }

    // 2. Secondary: Magic Bytes (Manual Fallback)
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return 'application/octet-stream';
    }
    $bytes = fread($handle, 12); // Read first 12 bytes
    fclose($handle);

    if ($bytes === false || strlen($bytes) < 4) {
        return 'application/octet-stream';
    }

    $hex = bin2hex($bytes);

    // Image signatures
    if (str_starts_with($hex, 'ffd8ff')) return 'image/jpeg';
    if (str_starts_with($hex, '89504e470d0a1a0a')) return 'image/png';
    if (str_starts_with($hex, '47494638')) return 'image/gif';
    if (str_starts_with($hex, '52494646') && substr($hex, 16, 8) === '57454250') return 'image/webp'; // RIFF....WEBP

    // Video signatures
    // MP4/QuickTime (ftyp) - often starts at offset 4
    if (substr($hex, 8, 8) === '66747970') return 'video/mp4'; // ftyp
    // MKV/WebM
    if (str_starts_with($hex, '1a45dfa3')) return 'video/x-matroska';

    // Optional: Command line file (if enabled/available) - User said optional optimization
    // exec("file -b --mime-type " . escapeshellarg($path), $output, $returnCode);
    // if ($returnCode === 0 && !empty($output[0])) {
    //    return trim($output[0]);
    // }

    // Return the finfo result if everything else fails (likely octet-stream)
    return $mime;
}

function processFile($sourcePath, $originalName, $fileSize, $user) {
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
        send_error_response('Blocked file type', 'BLOCKED_TYPE');
    }

    // Verify MIME type server-side with fallback
    $realMime = get_real_mime_type($sourcePath);

    // If still octet-stream, try to guess from extension if secure
    if ($realMime === 'application/octet-stream') {
        // Only trust extension if we really couldn't detect anything, but usually get_real_mime_type should catch it.
        // We will log this edge case.
        if (defined('DEBUG') && DEBUG) {
            app_log("MIME detection completely failed for $originalName, returned octet-stream.");
        }
    }

    // Log detection result
    if (defined('DEBUG') && DEBUG) {
        app_log("Detected MIME for $originalName: $realMime");
    }

    $isImage = str_starts_with($realMime, 'image/');
    $isVideo = str_starts_with($realMime, 'video/');

    if ($isImage) {
        if (!in_array($realMime, $allowedImages, true)) {
            send_error_response('Invalid image format: ' . $realMime, 'INVALID_FORMAT');
        }
        if ($fileSize > $maxImageBytes) {
            send_error_response('Image too large', 'TOO_LARGE');
        }
    } elseif ($isVideo) {
        if (!in_array($realMime, $allowedVideos, true)) {
            send_error_response('Invalid video format: ' . $realMime, 'INVALID_FORMAT');
        }
        if ($fileSize > $maxVideoBytes) {
            send_error_response('Video too large', 'TOO_LARGE');
        }
    } else {
        send_error_response('Unsupported media type: ' . $realMime, 'UNSUPPORTED_TYPE');
    }

    // Destinations
    $random = bin2hex(random_bytes(16));
    $filename = $random . '.' . $ext;
    $uploadDir = __DIR__ . '/../uploads';
    $targetOriginal = $uploadDir . '/original/' . $filename;

    // Ensure subdirectories exist
    if (!is_dir($uploadDir . '/original')) mkdir($uploadDir . '/original', 0777, true);
    if (!is_dir($uploadDir . '/thumbs')) mkdir($uploadDir . '/thumbs', 0777, true);
    if (!is_dir($uploadDir . '/preview')) mkdir($uploadDir . '/preview', 0777, true);

    // Move (or rename if from chunks)
    if (!rename($sourcePath, $targetOriginal)) {
        if (!copy($sourcePath, $targetOriginal)) {
            send_error_response('Failed to save file to ' . $targetOriginal, 'SAVE_ERROR');
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

    try {
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

            // Try to generate preview (high res, smart scaling)
            $previewSuccess = Media::generateVideoThumb($targetOriginal, $targetPreview, 1600);

            if ($previewSuccess) {
                // If preview generated, use it to make the thumbnail
                $thumbSuccess = Media::generateResized($targetPreview, $targetThumb, 480, 480);
            } else {
                // Fallback
                $thumbSuccess = Media::generateVideoThumb($targetOriginal, $targetThumb, 480);
            }
        }
    } catch (Exception $e) {
        error_log("Media generation failed: " . $e->getMessage());
    }

    // Fallbacks
    if (!$thumbSuccess) {
        if ($isImage) $dbThumb = $dbOriginal;
        else $dbThumb = 'assets/img/placeholder.svg';
    }
    if (!$previewSuccess) {
        if ($isImage) $dbPreview = $dbOriginal;
        else $dbPreview = $dbThumb;
    }

    // Save to DB
    $shareToken = bin2hex(random_bytes(32));
    // Updated for new schema: owner_user_id and default visibility=private
    $stmt = $pdo->prepare('INSERT INTO posts (user_id, owner_user_id, title, created_at, visibility, share_token, type, file_path, mime, size_bytes, width, height, thumb_path, preview_path)
        VALUES (:uid, :owner_uid, :title, :created, "private", :token, :type, :path, :mime, :size, :w, :h, :thumb, :preview)');

    $stmt->execute([
        ':uid' => $user['id'],
        ':owner_uid' => $user['id'],
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

    send_json_response(['success' => true, 'post_id' => (int)$pdo->lastInsertId()]);
}
