<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$user = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accent = trim($_POST['accent_color'] ?? '#4b8bff');
    $pageScale = (int)($_POST['page_scale'] ?? 100);
    $bgType = $_POST['bg_type'] ?? 'default';
    $bgValue = trim($_POST['bg_value'] ?? '');

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        flash('error', 'Invalid color format.');
        redirect('/admin/settings.php');
    }
    if ($pageScale < 50 || $pageScale > 200) {
        $pageScale = 100;
    }

    setting_set($pdo, 'accent_color', $accent);
    setting_set($pdo, 'page_scale', (string)$pageScale);
    setting_set($pdo, 'bg_type', $bgType);
    setting_set($pdo, 'bg_value', $bgValue);

    flash('success', 'Nastavitve shranjene.');
    redirect('/admin/settings.php');
}

render_header('Nastavitve', $user, 'settings');
render_flash($flash ?? null);

$currentAccent = setting_get($pdo, 'accent_color', '#4b8bff');
$currentScale = (int)setting_get($pdo, 'page_scale', '100');
$bgType = setting_get($pdo, 'bg_type', 'default');
$bgValue = setting_get($pdo, 'bg_value', '');
?>
<div class="settings-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1>Nastavitve</h1>

        <div class="form-group">
            <label>Barva poudarkov (Accent Color)</label>
            <div class="color-picker-wrapper">
                <input type="color" name="accent_color" value="<?php echo htmlspecialchars($currentAccent); ?>">
                <span class="color-value"><?php echo htmlspecialchars($currentAccent); ?></span>
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

        <button class="button" type="submit">Shrani spremembe</button>
    </form>
</div>
<?php
render_footer();
