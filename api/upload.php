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

// Config
$pdo = \App\Database::connect();
$maxImageGb = (float)Settings::get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = (float)Settings::get($pdo, 'max_video_gb', '5.0');
$maxFiles = (int)Settings::get($pdo, 'max_files_per_upload', '100');

$maxImageBytes = $maxImageGb * 1024 * 1024 * 1024;
$maxVideoBytes = $maxVideoGb * 1024 * 1024 * 1024;
$allowedImages = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$allowedVideos = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'];
$blockedExts = ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'exe', 'sh'];

$files = $_FILES['files'] ?? null;
if (!$files) {
    Response::error('No files received', 'NO_FILES');
}

// Normalize
$normalizedFiles = [];
if (is_array($files['name'])) {
    $count = count($files['name']);
    if ($count > $maxFiles) {
        Response::error("Max {$maxFiles} files allowed", 'TOO_MANY_FILES');
    }
    for ($i = 0; $i < $count; $i++) {
        $normalizedFiles[] = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];
    }
} else {
    $normalizedFiles[] = $files;
}

$results = [];
$finfo = new finfo(FILEINFO_MIME_TYPE);

foreach ($normalizedFiles as $file) {
    $res = ['name' => $file['name'], 'request_id' => $_SERVER['REQUEST_ID']];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $res['error'] = 'Upload error code: ' . $file['error'];
        $res['code'] = 'UPLOAD_ERR_' . $file['error'];
        $results[] = $res;
        continue;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, $blockedExts, true)) {
        $res['error'] = 'Blocked file type';
        $res['code'] = 'BLOCKED_TYPE';
        $results[] = $res;
        continue;
    }

    $mime = $finfo->file($file['tmp_name']) ?: '';
    $isImage = str_starts_with($mime, 'image/');
    $isVideo = str_starts_with($mime, 'video/');

    if ($isImage) {
        if (!in_array($mime, $allowedImages, true)) {
            $res['error'] = 'Invalid image format';
            $results[] = $res;
            continue;
        }
        if ($file['size'] > $maxImageBytes) {
            $res['error'] = 'Image too large';
            $results[] = $res;
            continue;
        }
    } elseif ($isVideo) {
        if (!in_array($mime, $allowedVideos, true)) {
            $res['error'] = 'Invalid video format';
            $results[] = $res;
            continue;
        }
        if ($file['size'] > $maxVideoBytes) {
            $res['error'] = 'Video too large';
            $results[] = $res;
            continue;
        }
    } else {
        $res['error'] = 'Unsupported media type';
        $results[] = $res;
        continue;
    }

    // Process
    $random = bin2hex(random_bytes(16));
    $filename = $random . '.' . $ext;

    $uploadDir = __DIR__ . '/../uploads';
    $targetOriginal = $uploadDir . '/original/' . $filename;
    $targetThumb = $uploadDir . '/thumbs/' . $random . '.webp';
    $targetPreview = $uploadDir . '/preview/' . $random . '.webp'; // Standardize preview to webp

    if (!move_uploaded_file($file['tmp_name'], $targetOriginal)) {
        $res['error'] = 'Failed to move file';
        $results[] = $res;
        continue;
    }

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
            copy($targetThumb, $targetPreview); // Simple copy for now
            $previewSuccess = true;
        }
    }

    // Fallbacks
    if (!$thumbSuccess) {
        if ($isImage) $dbThumb = $dbOriginal;
        else $dbThumb = 'assets/img/video_placeholder.jpg'; // Needs existence check?
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
        ':title' => pathinfo($file['name'], PATHINFO_FILENAME),
        ':created' => time(),
        ':token' => $shareToken,
        ':type' => $isImage ? 'image' : 'video',
        ':path' => $dbOriginal,
        ':mime' => $mime,
        ':size' => $file['size'],
        ':w' => $width,
        ':h' => $height,
        ':thumb' => $dbThumb,
        ':preview' => $dbPreview
    ]);

    $res['success'] = true;
    $res['post_id'] = (int)$pdo->lastInsertId();
    $results[] = $res;
}

Response::json(['results' => $results]);
