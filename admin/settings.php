<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$user = require_admin($pdo);

// Helper to parse ini values
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

$serverMaxUpload = return_bytes(ini_get('upload_max_filesize'));
$serverMaxPost = return_bytes(ini_get('post_max_size'));
$serverLimitBytes = min($serverMaxUpload, $serverMaxPost);

function format_bytes_short($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    return $bytes . ' B';
}

$serverLimitFormatted = format_bytes_short($serverLimitBytes);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accent = trim($_POST['accent_color'] ?? '#4b8bff');
    $pageScale = (int)($_POST['page_scale'] ?? 100);
    $bgType = $_POST['bg_type'] ?? 'default';
    $bgValue = trim($_POST['bg_value'] ?? '');

    // Upload Limits
    $maxImageGb = (float)($_POST['max_image_gb'] ?? 5.0);
    $maxVideoGb = (float)($_POST['max_video_gb'] ?? 5.0);
    $maxFiles = (int)($_POST['max_files_per_upload'] ?? 10);

    // Validation
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        flash('error', 'Invalid color format.');
        redirect('/admin/settings.php');
    }
    if ($pageScale < 50 || $pageScale > 200) {
        $pageScale = 100;
    }

    // Validate upload limits
    if ($maxImageGb < 0.1 || $maxImageGb > 50) $maxImageGb = 5.0;
    if ($maxVideoGb < 0.1 || $maxVideoGb > 50) $maxVideoGb = 5.0;
    if ($maxFiles < 1 || $maxFiles > 100) $maxFiles = 10;

    setting_set($pdo, 'accent_color', $accent);
    setting_set($pdo, 'page_scale', (string)$pageScale);
    setting_set($pdo, 'bg_type', $bgType);
    setting_set($pdo, 'bg_value', $bgValue);

    setting_set($pdo, 'max_image_gb', (string)$maxImageGb);
    setting_set($pdo, 'max_video_gb', (string)$maxVideoGb);
    setting_set($pdo, 'max_files_per_upload', (string)$maxFiles);

    flash('success', 'Nastavitve shranjene.');
    redirect('/admin/settings.php');
}

render_header('Nastavitve', $user, 'settings');
render_flash($flash ?? null);

$currentAccent = setting_get($pdo, 'accent_color', '#4b8bff');
$currentScale = (int)setting_get($pdo, 'page_scale', '100');
$bgType = setting_get($pdo, 'bg_type', 'default');
$bgValue = setting_get($pdo, 'bg_value', '');

$maxImageGb = (float)setting_get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = (float)setting_get($pdo, 'max_video_gb', '5.0');
$maxFiles = (int)setting_get($pdo, 'max_files_per_upload', '10');

// Calculate if warning needed
$appLimitBytes = max($maxImageGb, $maxVideoGb) * 1024 * 1024 * 1024;
$showServerWarning = $serverLimitBytes < $appLimitBytes;
?>
<div class="settings-page">
    <form class="card form" method="post" id="settingsForm">
        <?php echo csrf_field(); ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <h1 style="margin:0;">Nastavitve</h1>
        </div>

        <div class="form-group">
            <label>Barva poudarkov (Accent Color)</label>
            <div class="color-picker-wrapper">
                <input type="color" name="accent_color" value="<?php echo htmlspecialchars($currentAccent); ?>" onchange="document.getElementById('color-val').textContent = this.value">
                <span class="color-value" id="color-val"><?php echo htmlspecialchars($currentAccent); ?></span>
            </div>
        </div>

        <div class="form-group">
            <label>Velikost strani (%)</label>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="range" name="page_scale" min="70" max="150" value="<?php echo $currentScale; ?>" oninput="this.nextElementSibling.value = this.value + '%'">
                <output><?php echo $currentScale; ?>%</output>
            </div>
        </div>

        <div class="form-group">
            <label>Ozadje</label>
            <select name="bg_type" onchange="document.getElementById('bg-value-group').style.display = this.value === 'default' ? 'none' : 'block'">
                <option value="default" <?php echo $bgType === 'default' ? 'selected' : ''; ?>>Privzeto</option>
                <option value="color" <?php echo $bgType === 'color' ? 'selected' : ''; ?>>Barva</option>
                <option value="image" <?php echo $bgType === 'image' ? 'selected' : ''; ?>>Slika (URL)</option>
            </select>
        </div>

        <div class="form-group" id="bg-value-group" style="display: <?php echo $bgType === 'default' ? 'none' : 'block'; ?>">
            <label>Vrednost (Hex barva ali URL slike)</label>
            <input type="text" name="bg_value" value="<?php echo htmlspecialchars($bgValue); ?>" placeholder="#000000 ali https://...">
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 2rem 0;">

        <h3>Upload limiti</h3>
        <p class="text-muted" style="margin-bottom: 1rem; font-size: 0.9rem;">Določi največjo velikost datotek za slike in videe.</p>

        <?php if ($showServerWarning): ?>
        <div class="alert warning" style="margin-bottom: 1rem; font-size: 0.9rem; padding: 0.5rem 1rem; background: #3d3412; color: #ffd54f; border-radius: 4px;">
            ⚠️ <strong>Opozorilo:</strong> Strežniški limit (<?php echo $serverLimitFormatted; ?>) je nižji od nastavitev aplikacije. Upload večjih datotek bo morda zavrnjen s strani strežnika.
        </div>
        <?php endif; ?>

        <div class="grid-2">
            <div class="form-group">
                <label>Max slika (GB)</label>
                <input type="number" name="max_image_gb" step="0.1" min="0.1" max="50" value="<?php echo $maxImageGb; ?>">
            </div>
            <div class="form-group">
                <label>Max video (GB)</label>
                <input type="number" name="max_video_gb" step="0.1" min="0.1" max="50" value="<?php echo $maxVideoGb; ?>">
            </div>
        </div>

        <div class="form-group">
            <label>Max datotek naenkrat</label>
            <input type="number" name="max_files_per_upload" min="1" max="100" value="<?php echo $maxFiles; ?>">
            <small class="text-muted">Strežnik trenutno: Upload limit ≈ <?php echo $serverLimitFormatted; ?></small>
        </div>

        <div style="margin-top: 2rem;">
            <button class="button" type="submit">Shrani spremembe</button>
        </div>
    </form>
</div>
<style>
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 600px) { .grid-2 { grid-template-columns: 1fr; } }
</style>
<script>
    // Simple unsaved changes indicator
    const form = document.getElementById('settingsForm');
    const btn = form.querySelector('button[type="submit"]');
    const initialData = new FormData(form);

    form.addEventListener('change', () => {
        btn.textContent = 'Shrani spremembe *';
    });
</script>
<?php
render_footer();
