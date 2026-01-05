<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/layout.php';

$user = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accent = trim($_POST['accent_color'] ?? '#4b8bff');
    $fontSize = trim($_POST['font_size'] ?? '16px');

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
        flash('error', 'Invalid color format.');
        redirect('/settings.php');
    }

    setting_set($pdo, 'accent_color', $accent);
    setting_set($pdo, 'font_size', $fontSize);

    flash('success', 'Nastavitve shranjene.');
    redirect('/settings.php');
}

render_header('Nastavitve', $user, 'settings');
render_flash($flash ?? null);

$currentAccent = setting_get($pdo, 'accent_color', '#4b8bff');
$currentSize = setting_get($pdo, 'font_size', '16px');
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
            <label>Velikost pisave (Osnovna velikost)</label>
            <select name="font_size">
                <option value="14px" <?php echo $currentSize === '14px' ? 'selected' : ''; ?>>Majhna (14px)</option>
                <option value="16px" <?php echo $currentSize === '16px' ? 'selected' : ''; ?>>Srednja (16px)</option>
                <option value="18px" <?php echo $currentSize === '18px' ? 'selected' : ''; ?>>Velika (18px)</option>
                <option value="20px" <?php echo $currentSize === '20px' ? 'selected' : ''; ?>>Zelo velika (20px)</option>
            </select>
        </div>

        <button class="button" type="submit">Shrani spremembe</button>
    </form>
</div>
<?php
render_footer();
