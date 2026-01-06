<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php'; // For render_header

use App\Auth;
use App\Settings;
use App\Database;

$user = Auth::requireLogin();
$pdo = Database::connect();

// If POST, forward to API logic? No, JS Uploader uses /api/upload.php.
// If this file receives POST, it might be a legacy form submit, but uploader.js uses AJAX.
// So we just render the UI.

$maxImageGb = (float)Settings::get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = (float)Settings::get($pdo, 'max_video_gb', '5.0');
$maxFiles = (int)Settings::get($pdo, 'max_files_per_upload', '100');

$maxImageBytes = $maxImageGb * 1024 * 1024 * 1024;
$maxVideoBytes = $maxVideoGb * 1024 * 1024 * 1024;

render_header('Naloži datoteke', $user, 'upload');
?>
<div class="upload-page">
    <div class="uploader-container">
        <div class="drop-zone" id="dropZone">
            <div class="drop-content">
                <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                    <polyline points="17 8 12 3 7 8" />
                    <line x1="12" y1="3" x2="12" y2="15" />
                </svg>
                <h2 class="upload-title">Povleči datoteke sem</h2>
                <p class="upload-subtitle">ali tapni za izbor (max <?php echo $maxFiles; ?>)</p>
                <input type="file" id="fileInput" name="files[]" multiple accept="image/*,video/*" class="file-input" style="display:none;">
            </div>
            <div class="upload-limits" style="margin-top:1.5rem; color:var(--muted); font-size:0.9rem;">
                Max slika: <?php echo $maxImageGb; ?> GB • Max video: <?php echo $maxVideoGb; ?> GB • Max: <?php echo $maxFiles; ?> datotek
            </div>
        </div>
        <div class="upload-list" id="uploadList"></div>
    </div>
</div>
<script>
    // Trigger file input on click
    document.getElementById('dropZone').addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') {
            document.getElementById('fileInput').click();
        }
    });

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
