<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/layout.php';

$user = require_login($pdo);
$config = app_config();

// Check PHP limits
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $responses = [];
    $files = $_FILES['files'] ?? null;
    if (!$files) {
        http_response_code(400);
        echo json_encode(['error' => 'No files uploaded']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileCount = count($files['name']);
    if ($fileCount > 10) {
        http_response_code(400);
        echo json_encode(['error' => 'Maximum 10 files']);
        exit;
    }

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $responses[] = ['name' => $files['name'][$i], 'error' => 'Upload failed'];
            continue;
        }
        $originalName = $files['name'][$i];
        $tmpName = $files['tmp_name'][$i];
        $size = (int)$files['size'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (in_array($ext, $config['blocked_exts'], true)) {
            $responses[] = ['name' => $originalName, 'error' => 'File type blocked'];
            continue;
        }
        $mime = $finfo->file($tmpName) ?: '';
        $mediaType = detect_media_type($mime);
        if ($mediaType === 'image' && !in_array($mime, $config['allowed_images'], true)) {
            $responses[] = ['name' => $originalName, 'error' => 'Invalid image type'];
            continue;
        }
        if ($mediaType === 'video' && !in_array($mime, $config['allowed_videos'], true)) {
            $responses[] = ['name' => $originalName, 'error' => 'Invalid video type'];
            continue;
        }
        if (!$mediaType) {
            $responses[] = ['name' => $originalName, 'error' => 'Unsupported file'];
            continue;
        }
        if ($mediaType === 'image' && $size > $config['max_image_size']) {
            $responses[] = ['name' => $originalName, 'error' => 'Image too large'];
            continue;
        }

        $random = bin2hex(random_bytes(16));
        $filename = $random . '.' . $ext;
        $target = 'uploads/' . $filename;
        $thumbTarget = 'thumbs/' . $random . '.jpg';

        if (!move_uploaded_file($tmpName, __DIR__ . '/' . $target)) {
            $responses[] = ['name' => $originalName, 'error' => 'Failed to move file'];
            continue;
        }

        $width = null;
        $height = null;
        $duration = null;
        $thumbSuccess = false;
        if ($mediaType === 'image') {
            $info = getimagesize(__DIR__ . '/' . $target);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }
            $thumbSuccess = generate_image_thumb(__DIR__ . '/' . $target, __DIR__ . '/' . $thumbTarget);
        } else {
            $thumbSuccess = generate_video_thumb(__DIR__ . '/' . $target, __DIR__ . '/' . $thumbTarget);
        }

        if (!$thumbSuccess) {
            $thumbTarget = 'thumbs/placeholder.jpg';
            if (!file_exists(__DIR__ . '/' . $thumbTarget)) {
                generate_placeholder_thumb(__DIR__ . '/' . $thumbTarget);
            }
        }

        $shareToken = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, description, created_at, visibility, share_token, type, file_path, mime, size_bytes, width, height, duration_sec, thumb_path)
            VALUES (:user_id, :title, :description, :created_at, :visibility, :share_token, :type, :file_path, :mime, :size_bytes, :width, :height, :duration_sec, :thumb_path)');
        $stmt->execute([
            ':user_id' => $user['id'],
            ':title' => pathinfo($originalName, PATHINFO_FILENAME),
            ':description' => null,
            ':created_at' => time(),
            ':visibility' => 'public',
            ':share_token' => $shareToken,
            ':type' => $mediaType,
            ':file_path' => $target,
            ':mime' => $mime,
            ':size_bytes' => $size,
            ':width' => $width,
            ':height' => $height,
            ':duration_sec' => $duration,
            ':thumb_path' => $thumbTarget,
        ]);

        $responses[] = ['name' => $originalName, 'success' => true, 'post_id' => (int)$pdo->lastInsertId()];
    }

    header('Content-Type: application/json');
    echo json_encode(['results' => $responses]);
    exit;
}

render_header('Upload', $user, 'upload');
render_flash($flash ?? null);
?>
<div class="upload-page">
    <div class="uploader" data-max="10">
        <div class="drop-zone">
            <p>Povlecite datoteke sem ali kliknite za izbiro (max 10)</p>
            <p class="text-muted small">Server limits: Upload <?php echo $maxUpload; ?>, Post <?php echo $maxPost; ?>. App limit: 50GB.</p>
            <input type="file" name="files[]" multiple accept="image/*,video/*">
        </div>
        <div class="upload-list"></div>
    </div>
</div>
<script src="/assets/js/uploader.js"></script>
<?php
render_footer();
