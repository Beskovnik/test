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
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    if ($mime && $mime !== 'application/octet-stream' && $mime !== 'application/x-empty') {
        return $mime;
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) return 'application/octet-stream';
    $bytes = fread($handle, 12);
    fclose($handle);
    if ($bytes === false || strlen($bytes) < 4) return 'application/octet-stream';
    $hex = bin2hex($bytes);
    if (str_starts_with($hex, 'ffd8ff')) return 'image/jpeg';
    if (str_starts_with($hex, '89504e470d0a1a0a')) return 'image/png';
    if (str_starts_with($hex, '47494638')) return 'image/gif';
    if (str_starts_with($hex, '52494646') && substr($hex, 16, 8) === '57454250') return 'image/webp';
    if (substr($hex, 8, 8) === '66747970') return 'video/mp4';
    if (str_starts_with($hex, '1a45dfa3')) return 'video/x-matroska';
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

    $realMime = get_real_mime_type($sourcePath);
    if (defined('DEBUG') && DEBUG) {
        app_log("Detected MIME for $originalName: $realMime");
    }

    // Sentinel Security Fix: Enforce extension based on MIME type
    // This prevents polyglot attacks where a file is a valid image but has a malicious extension (e.g. .php.jpg)
    // or an extension that bypasses the blocklist (e.g. .php5).
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-matroska' => 'mkv',
    ];

    if (!isset($mimeToExt[$realMime])) {
        send_error_response('Unsupported media type', 'UNSUPPORTED_TYPE');
    }

    $safeExt = $mimeToExt[$realMime];

    $isImage = str_starts_with($realMime, 'image/');
    $isVideo = str_starts_with($realMime, 'video/');

    if ($isImage) {
        if (!in_array($realMime, $allowedImages, true)) send_error_response('Invalid image format', 'INVALID_FORMAT');
        if ($fileSize > $maxImageBytes) send_error_response('Image too large', 'TOO_LARGE');
    } elseif ($isVideo) {
        if (!in_array($realMime, $allowedVideos, true)) send_error_response('Invalid video format', 'INVALID_FORMAT');
        if ($fileSize > $maxVideoBytes) send_error_response('Video too large', 'TOO_LARGE');
    }

    // Destinations
    $random = bin2hex(random_bytes(16));
    // Use the safe extension derived from MIME type, NOT the user-provided one
    $filename = $random . '.' . $safeExt;

    // Paths
    $uploadDir = __DIR__ . '/../uploads';
    $optimizedDir = __DIR__ . '/../optimized'; // New optimized root

    $targetOriginal = $uploadDir . '/original/' . $filename;

    // Ensure dirs
    if (!is_dir($uploadDir . '/original')) mkdir($uploadDir . '/original', 0777, true);
    if (!is_dir($uploadDir . '/thumbs')) mkdir($uploadDir . '/thumbs', 0777, true);
    if (!is_dir($optimizedDir)) mkdir($optimizedDir, 0777, true);

    // Save Original
    if (!rename($sourcePath, $targetOriginal)) {
        if (!copy($sourcePath, $targetOriginal)) {
            send_error_response('Failed to save file', 'SAVE_ERROR');
        }
    }

    // --- OPTIMIZATION PIPELINE ---
    $width = null;
    $height = null;
    $thumbSuccess = false;
    $optimizedSuccess = false;

    // Database Paths (relative to web root)
    $dbOriginal = 'uploads/original/' . $filename;

    // Default Thumbs/Optimized filenames (prefer WEBP for images)
    $thumbName = $random . '.webp';
    $optimizedName = $random . '.webp';

    // For video, we use JPG thumbs
    if ($isVideo) {
        $thumbName = $random . '.jpg';
    }

    $targetThumb = $uploadDir . '/thumbs/' . $thumbName;
    $targetOptimized = $optimizedDir . '/' . $optimizedName;

    $dbThumb = 'uploads/thumbs/' . $thumbName;
    $dbOptimized = 'optimized/' . $optimizedName;

    try {
        // NEW: Get Preview Max KB limit
        $previewMaxKb = (int)Settings::get($pdo, 'preview_max_kb', '100');
        $maxBytes = $previewMaxKb * 1024;

        if ($isImage) {
            // Extract Metadata
            $metadata = Media::getMetadata($targetOriginal);
            $width = $metadata['width'];
            $height = $metadata['height'];

            // 1. Generate Thumb/Preview (Strict KB limit)
            // Use 640px max width for thumbnails as requested (320-640 range)
            $thumbSuccess = Media::generatePreviewUnderBytes($targetOriginal, $targetThumb, $maxBytes, 640, 80);

            // 2. Generate Optimized (1920px) - Strict Requirement
            // Quality 82 for WEBP/JPEG
            $optimizedSuccess = Media::generateResized($targetOriginal, $targetOptimized, 1920, 1920, 82);

        } else {
            // Video: Only Thumbs (No transcoding of video file itself)
            // But we need a thumb for the grid

            // Try generate thumb
            $thumbSuccess = Media::generateVideoThumb($targetOriginal, $targetThumb, 480);

            // NOTE: For video thumbnails we might want to apply the KB limit too,
            // but generateVideoThumb uses ffmpeg directly.
            // We could re-process the generated thumb to ensure it meets the size limit.
            if ($thumbSuccess && file_exists($targetThumb)) {
                $vThumbSize = filesize($targetThumb);
                if ($vThumbSize > $maxBytes) {
                    // Re-process video thumb to fit size
                    Media::generatePreviewUnderBytes($targetThumb, $targetThumb, $maxBytes, 480, 80);
                }
            }

            // No optimized video transcoding (as requested)
            $dbOptimized = null;
            $optimizedSuccess = false;
        }
    } catch (Exception $e) {
        error_log("Media generation failed: " . $e->getMessage());
    }

    // Fallbacks logic for DB
    // If thumb failed, use original (if image) or placeholder (if video)
    if (!$thumbSuccess) {
        if ($isImage) $dbThumb = $dbOriginal;
        else $dbThumb = 'assets/img/placeholder.svg';
    }

    // If optimized failed (or is video), use original
    if ($isImage && !$optimizedSuccess) {
        $dbOptimized = $dbOriginal;
    }
    // For video, dbOptimized remains null or we set to original?
    // Let's set to NULL if it doesn't exist, and handle fallback in view.
    // Or set to original.
    if ($isVideo) {
        $dbOptimized = $dbOriginal; // View page will use this
    }

    // DB Insert
    $shareToken = bin2hex(random_bytes(32));

    // We update `preview_path` to be the same as `optimized_path` for backward compatibility
    $dbPreview = $dbOptimized;

    // Get final metadata for DB (taken_at, etc)
    // For Video, we might default to filemtime or current time if extraction is hard
    $photoTakenAt = time();
    $exifJson = null;

    if ($isImage && isset($metadata)) {
        $photoTakenAt = $metadata['taken_at'] ?? time();
        $exifJson = json_encode($metadata);
    } else {
        $photoTakenAt = filemtime($targetOriginal);
    }

    $stmt = $pdo->prepare('INSERT INTO posts (user_id, owner_user_id, title, created_at, visibility, share_token, type, file_path, mime, size_bytes, width, height, thumb_path, preview_path, optimized_path, photo_taken_at, exif_json)
        VALUES (:uid, :owner_uid, :title, :created, "private", :token, :type, :path, :mime, :size, :w, :h, :thumb, :preview, :optimized, :taken, :exif)');

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
        ':preview' => $dbPreview,
        ':optimized' => $dbOptimized,
        ':taken' => $photoTakenAt,
        ':exif' => $exifJson
    ]);

    send_json_response(['success' => true, 'post_id' => (int)$pdo->lastInsertId()]);
}
