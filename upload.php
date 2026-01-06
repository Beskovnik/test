<?php
// Ensure JSON response for API calls
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Disable display_errors to prevent HTML pollution in JSON
    ini_set('display_errors', '0');
    header('Content-Type: application/json; charset=utf-8');
}

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/layout.php';

// Custom Error Handler for JSON requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        $requestId = $_SERVER['REQUEST_ID'] ?? uniqid('req_', true);
        http_response_code(500);
        echo json_encode([
            'results' => [[
                'error' => "PHP Error: $errstr",
                'code' => 'PHP_ERR',
                'request_id' => $requestId
            ]]
        ]);
        exit;
    });

    // Exception Handler
    set_exception_handler(function($e) {
        $requestId = $_SERVER['REQUEST_ID'] ?? uniqid('req_', true);
        http_response_code(500);
        echo json_encode([
            'results' => [[
                'error' => $e->getMessage(),
                'code' => 'EXCEPTION',
                'request_id' => $requestId
            ]]
        ]);
        exit;
    });
}

// Check Login manually to return JSON instead of redirect
$user = current_user($pdo);
if (!$user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(401);
    echo json_encode(['results' => [[
        'error' => 'Prijava je potrebna',
        'code' => 'UNAUTHORIZED'
    ]]]);
    exit;
} elseif (!$user) {
    redirect('/login.php');
}

$config = app_config();

// Get settings
$maxImageGb = (float)setting_get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = (float)setting_get($pdo, 'max_video_gb', '5.0');
$maxFiles = (int)setting_get($pdo, 'max_files_per_upload', '10');

// Calculate bytes
$maxImageBytes = $maxImageGb * 1024 * 1024 * 1024;
$maxVideoBytes = $maxVideoGb * 1024 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = uniqid('req_', true);

    // Check CSRF
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        http_response_code(403);
        echo json_encode(['results' => [[
            'error' => 'Neveljaven varnostni žeton (CSRF)',
            'code' => 'CSRF_INVALID',
            'request_id' => $requestId
        ]]]);
        exit;
    }

    // Check Permissions
    if (!is_writable(__DIR__ . '/uploads')) {
        http_response_code(500);
        echo json_encode(['results' => [[
            'error' => 'Upload mapa ni zapisljiva',
            'code' => 'PERM_DENIED',
            'request_id' => $requestId
        ]]]);
        exit;
    }

    $responses = [];
    $files = $_FILES['files'] ?? null;

    // Return empty success if no files (sometimes happens with empty POST)
    if (!$files) {
        http_response_code(400);
        echo json_encode(['results' => [[
            'error' => 'Nobena datoteka ni bila prejeta',
            'code' => 'NO_FILES',
            'request_id' => $requestId
        ]]]);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    // Normalize files array if multiple
    $normalizedFiles = [];
    if (is_array($files['name'])) {
        $count = count($files['name']);
        if ($count > $maxFiles) {
             // We allow > maxFiles in parallel requests, but if a single request has > max, block it.
             // With the new 100 limit, this is less likely.
            http_response_code(400);
            echo json_encode(['results' => [[
                'error' => "Preveč datotek v enem zahtevku (Max {$maxFiles})",
                'code' => 'TOO_MANY_FILES',
                'request_id' => $requestId
            ]]]);
            exit;
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

    foreach ($normalizedFiles as $file) {
        $res = [
            'name' => $file['name'],
            'request_id' => $requestId
        ];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $codeMap = [
                UPLOAD_ERR_INI_SIZE => 'File too large (ini)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form)',
                UPLOAD_ERR_PARTIAL => 'Partial upload',
                UPLOAD_ERR_NO_FILE => 'No file',
                UPLOAD_ERR_NO_TMP_DIR => 'No tmp dir',
                UPLOAD_ERR_CANT_WRITE => 'Cant write',
                UPLOAD_ERR_EXTENSION => 'Extension error'
            ];
            $res['error'] = 'Upload failed: ' . ($codeMap[$file['error']] ?? 'Code ' . $file['error']);
            $res['code'] = 'UPLOAD_ERR_' . $file['error'];
            $responses[] = $res;
            continue;
        }
        $originalName = $file['name'];
        $tmpName = $file['tmp_name'];
        $size = (int)$file['size'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($ext, $config['blocked_exts'], true)) {
            $res['error'] = 'File type blocked';
            $res['code'] = 'BLOCKED_TYPE';
            $responses[] = $res;
            continue;
        }

        $mime = $finfo->file($tmpName) ?: '';
        $mediaType = detect_media_type($mime);

        if ($mediaType === 'image') {
            if (!in_array($mime, $config['allowed_images'], true)) {
                $res['error'] = 'Invalid image type';
                $res['code'] = 'INVALID_TYPE';
                $responses[] = $res;
                continue;
            }
            if ($size > $maxImageBytes) {
                $res['error'] = "Image too large (Max {$maxImageGb} GB)";
                $res['code'] = 'TOO_LARGE';
                $responses[] = $res;
                continue;
            }
        } elseif ($mediaType === 'video') {
            if (!in_array($mime, $config['allowed_videos'], true)) {
                $res['error'] = 'Invalid video type';
                $res['code'] = 'INVALID_TYPE';
                $responses[] = $res;
                continue;
            }
            if ($size > $maxVideoBytes) {
                $res['error'] = "Video too large (Max {$maxVideoGb} GB)";
                $res['code'] = 'TOO_LARGE';
                $responses[] = $res;
                continue;
            }
        } else {
            $res['error'] = 'Unsupported file';
            $res['code'] = 'UNSUPPORTED';
            $responses[] = $res;
            continue;
        }

        $random = bin2hex(random_bytes(16));
        $filename = $random . '.' . $ext;

        // Define paths
        $targetOriginal = 'uploads/original/' . $filename;
        $targetPreview = 'uploads/preview/' . $random . '.webp'; // WebP for preview
        $targetThumb = 'uploads/thumbs/' . $random . '.webp'; // WebP for thumb

        // Move Original
        if (!move_uploaded_file($tmpName, __DIR__ . '/' . $targetOriginal)) {
            $res['error'] = 'Failed to move file';
            $res['code'] = 'MOVE_FAILED';
            $responses[] = $res;
            continue;
        }

        $width = null;
        $height = null;
        $duration = null;
        $thumbSuccess = false;
        $previewSuccess = false;

        if ($mediaType === 'image') {
            $info = getimagesize(__DIR__ . '/' . $targetOriginal);
            if ($info) {
                $width = $info[0];
                $height = $info[1];
            }
            // Generate Thumb (420px)
            $thumbSuccess = generate_image_resized(__DIR__ . '/' . $targetOriginal, __DIR__ . '/' . $targetThumb, 420, 420);

            // Generate Preview (1600px)
            $previewSuccess = generate_image_resized(__DIR__ . '/' . $targetOriginal, __DIR__ . '/' . $targetPreview, 1600, 1600);

        } else {
            // For video, we still generate a JPG/WebP thumb/preview (poster)
            // But we might want to keep the "original" extension for thumb if we were using it before,
            // but the new plan says WebP thumbs.
            $targetThumb = 'uploads/thumbs/' . $random . '.jpg'; // FFmpeg usually outputs jpg easily
            $targetPreview = 'uploads/preview/' . $random . '.jpg';

            // Generate Thumb
            $thumbSuccess = generate_video_thumb(__DIR__ . '/' . $targetOriginal, __DIR__ . '/' . $targetThumb);
            // We can just use the thumb as preview for video or generate a higher res one
            // Let's generate a higher res poster for preview
            if (is_ffmpeg_available()) {
                $cmd = sprintf(
                    'ffmpeg -y -ss 1 -i %s -frames:v 1 -vf "scale=1600:-1" %s 2>&1',
                    escapeshellarg(__DIR__ . '/' . $targetOriginal),
                    escapeshellarg(__DIR__ . '/' . $targetPreview)
                );
                shell_exec($cmd);
                $previewSuccess = file_exists(__DIR__ . '/' . $targetPreview);
            }
        }

        // Fallbacks
        if (!$thumbSuccess) {
            if ($mediaType === 'image') {
                $targetThumb = $targetOriginal; // Fallback to original
            } else {
                $targetThumb = 'thumbs/placeholder.jpg'; // We should probably move this placeholder to assets or something
                if (!file_exists(__DIR__ . '/' . $targetThumb)) {
                     generate_placeholder_thumb(__DIR__ . '/' . $targetThumb);
                }
            }
        }

        if (!$previewSuccess) {
             if ($mediaType === 'image') {
                $targetPreview = $targetOriginal;
             } else {
                $targetPreview = $targetThumb;
             }
        }

        $shareToken = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare('INSERT INTO posts (user_id, title, description, created_at, visibility, share_token, type, file_path, mime, size_bytes, width, height, duration_sec, thumb_path, preview_path)
            VALUES (:user_id, :title, :description, :created_at, :visibility, :share_token, :type, :file_path, :mime, :size_bytes, :width, :height, :duration_sec, :thumb_path, :preview_path)');
        $stmt->execute([
            ':user_id' => $user['id'],
            ':title' => pathinfo($originalName, PATHINFO_FILENAME),
            ':description' => null,
            ':created_at' => time(),
            ':visibility' => 'public',
            ':share_token' => $shareToken,
            ':type' => $mediaType,
            ':file_path' => $targetOriginal,
            ':mime' => $mime,
            ':size_bytes' => $size,
            ':width' => $width,
            ':height' => $height,
            ':duration_sec' => $duration,
            ':thumb_path' => $targetThumb,
            ':preview_path' => $targetPreview,
        ]);

        $res['success'] = true;
        $res['post_id'] = (int)$pdo->lastInsertId();
        $responses[] = $res;
    }

    echo json_encode(['results' => $responses]);
    exit;
}

render_header('Naloži datoteke', $user, 'upload');
render_flash($flash ?? null);
?>
<div class="upload-page">
    <div class="uploader-container">
        <!-- Main Upload Area -->
        <div class="drop-zone" id="dropZone">
            <div class="drop-content">
                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <h2 class="upload-title">Povleci datoteke sem</h2>
                <p class="upload-subtitle">ali tapni za izbor (max <?php echo $maxFiles; ?>)</p>
                <input type="file" id="fileInput" name="files[]" multiple accept="image/*,video/*" class="file-input">
            </div>
            <div class="upload-limits text-muted">
                Max slika: <?php echo $maxImageGb; ?> GB • Max video: <?php echo $maxVideoGb; ?> GB • Max: <?php echo $maxFiles; ?> datotek
            </div>
        </div>

        <!-- File List -->
        <div class="upload-list" id="uploadList">
            <!-- Files will be added here via JS -->
        </div>
    </div>
</div>
<script>
    window.APP_LIMITS = {
        maxFiles: <?php echo $maxFiles; ?>,
        maxImageBytes: <?php echo $maxImageBytes; ?>,
        maxVideoBytes: <?php echo $maxVideoBytes; ?>,
        maxImageGb: <?php echo $maxImageGb; ?>,
        maxVideoGb: <?php echo $maxVideoGb; ?>
    };
</script>
<script src="/assets/js/uploader.js"></script>
<?php
render_footer();
