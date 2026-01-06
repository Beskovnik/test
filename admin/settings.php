<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Settings;
use App\Database;
use App\Audit;

$user = Auth::requireAdmin();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Limits
    $maxImageGb = (float)$_POST['max_image_gb'];
    $maxVideoGb = (float)$_POST['max_video_gb'];
    $maxFiles = (int)$_POST['max_files_per_upload'];

    // Validation logic...
    if ($maxFiles > 100) $maxFiles = 100;

    Settings::set($pdo, 'max_image_gb', (string)$maxImageGb);
    Settings::set($pdo, 'max_video_gb', (string)$maxVideoGb);
    Settings::set($pdo, 'max_files_per_upload', (string)$maxFiles);

    // UI Settings
    Settings::set($pdo, 'accent_color', $_POST['accent_color']);

    Audit::log($pdo, $user['id'], 'settings_update', 'Updated settings');

    header('Location: /admin/settings.php');
    exit;
}

$maxImageGb = Settings::get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = Settings::get($pdo, 'max_video_gb', '5.0');
$maxFiles = Settings::get($pdo, 'max_files_per_upload', '100');
$accent = Settings::get($pdo, 'accent_color', '#4b8bff');

require __DIR__ . '/../includes/layout.php';
render_header('Nastavitve', $user, 'settings');
?>
<div class="admin-page">
    <h1>Nastavitve</h1>
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>

        <h3>Upload Limiti</h3>
        <div class="grid-2">
            <div>
                <label>Max Slika (GB)</label>
                <input type="number" step="0.1" name="max_image_gb" value="<?php echo $maxImageGb; ?>">
            </div>
            <div>
                <label>Max Video (GB)</label>
                <input type="number" step="0.1" name="max_video_gb" value="<?php echo $maxVideoGb; ?>">
            </div>
            <div>
                <label>Max Datotek naenkrat</label>
                <input type="number" max="100" name="max_files_per_upload" value="<?php echo $maxFiles; ?>">
            </div>
        </div>

        <h3>Videz</h3>
        <label>Poudarek (Accent)</label>
        <input type="color" name="accent_color" value="<?php echo $accent; ?>">

        <div class="actions" style="margin-top:2rem;">
            <button class="button" type="submit">Shrani</button>
        </div>
    </form>
</div>
<?php
render_footer();
