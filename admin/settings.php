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

    $allowedUiScales = ['0.8', '0.9', '1.0', '1.1', '1.2'];

    // Limits
    $maxImageGb = str_replace(',', '.', (string)$_POST['max_image_gb']);
    $maxVideoGb = str_replace(',', '.', (string)$_POST['max_video_gb']);
    $maxFiles = (int)$_POST['max_files_per_upload'];
    $uiScale = str_replace(',', '.', (string)$_POST['ui_scale']);

    // Validation logic...
    if ($maxFiles > 100) $maxFiles = 100;

    // Validate UI Scale
    $uiScaleVal = (float)$uiScale;
    $uiScale = number_format($uiScaleVal, 1, '.', '');
    if (!in_array($uiScale, $allowedUiScales, true)) {
        if ($uiScaleVal <= 0.8) {
            $uiScale = '0.8';
        } elseif ($uiScaleVal >= 1.2) {
            $uiScale = '1.2';
        } else {
            $uiScale = '1.0';
        }
    }

    Settings::set($pdo, 'max_image_gb', $maxImageGb);
    Settings::set($pdo, 'max_video_gb', $maxVideoGb);
    Settings::set($pdo, 'max_files_per_upload', (string)$maxFiles);

    // Thumb Settings
    Settings::set($pdo, 'thumb_width', (string)(int)$_POST['thumb_width']);
    Settings::set($pdo, 'thumb_height', (string)(int)$_POST['thumb_height']);
    Settings::set($pdo, 'thumb_quality', (string)(int)$_POST['thumb_quality']);

    // UI Settings
    Settings::set($pdo, 'accent_color', $_POST['accent_color']);
    Settings::set($pdo, 'ui_scale', $uiScale);

    // ARSO Weather Settings
    Settings::set($pdo, 'weather_arso_location', $_POST['weather_arso_location']);
    Settings::set($pdo, 'weather_arso_station_id', $_POST['weather_arso_station_id']);

    Audit::log($pdo, $user['id'], 'settings_update', 'Updated settings');

    header('Location: /admin/settings.php');
    exit;
}

$maxImageGb = Settings::get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = Settings::get($pdo, 'max_video_gb', '5.0');
$maxFiles = Settings::get($pdo, 'max_files_per_upload', '100');
$accent = Settings::get($pdo, 'accent_color', '#4b8bff');
$uiScale = Settings::get($pdo, 'ui_scale', '1.0');
$allowedUiScales = ['0.8', '0.9', '1.0', '1.1', '1.2'];
$uiScale = number_format((float)str_replace(',', '.', (string)$uiScale), 1, '.', '');
if (!in_array($uiScale, $allowedUiScales, true)) {
    $uiScale = '1.0';
}

// Weather Defaults
$arsoLoc = Settings::get($pdo, 'weather_arso_location', 'Ljubljana');
$arsoStation = Settings::get($pdo, 'weather_arso_station_id', 'LJUBL-ANA_BEZIGRAD');

require __DIR__ . '/../includes/layout.php';
render_header('Nastavitve', $user, 'settings');
?>
<div class="admin-page">
    <h1>Nastavitve</h1>
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>

        <h3>Vreme (ARSO)</h3>
        <p class="muted" style="margin-bottom:1rem;">Integracija z Agencijo RS za okolje (vreme.arso.gov.si).</p>

        <div class="grid-2">
            <div>
                <label>Lokacija (Napoved)</label>
                <input type="text" name="weather_arso_location" list="arso-locs" value="<?php echo htmlspecialchars($arsoLoc); ?>" placeholder="npr. Ljubljana">
                <datalist id="arso-locs">
                    <!-- Populated via JS -->
                </datalist>
                <small class="muted">Za pridobitev ID-ja lokacije za napoved.</small>
            </div>
            <div>
                <label>ID Postaje (Zgodovina/Opazovanja)</label>
                <input type="text" name="weather_arso_station_id" value="<?php echo htmlspecialchars($arsoStation); ?>" placeholder="npr. LJUBL-ANA_BEZIGRAD">
                <small class="muted">ID avtomatske postaje za zgodovinske podatke (XML).</small>
            </div>
        </div>

        <h3 style="margin-top:2rem;">Upload Limiti</h3>
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

        <h3>Optimizacija Slik (Thumbnails)</h3>
        <div class="grid-2">
            <div>
                <label>Thumb Širina (px)</label>
                <input type="number" name="thumb_width" value="<?php echo Settings::get($pdo, 'thumb_width', '480'); ?>">
            </div>
            <div>
                <label>Thumb Višina (px)</label>
                <input type="number" name="thumb_height" value="<?php echo Settings::get($pdo, 'thumb_height', '480'); ?>">
            </div>
            <div>
                <label>Kakovost (%)</label>
                <input type="number" min="10" max="100" name="thumb_quality" value="<?php echo Settings::get($pdo, 'thumb_quality', '80'); ?>">
            </div>
        </div>

        <h3>Videz</h3>
        <label>Poudarek (Accent)</label>
        <input type="color" name="accent_color" value="<?php echo $accent; ?>">

        <div style="margin-top:1rem;">
            <label>UI Scale (Velikost vmesnika)</label>
            <select name="ui_scale" class="form-control" style="max-width:200px;">
                <option value="0.8" <?php if($uiScale == '0.8') echo 'selected'; ?>>80% (Manjše)</option>
                <option value="0.9" <?php if($uiScale == '0.9') echo 'selected'; ?>>90%</option>
                <option value="1.0" <?php if($uiScale == '1.0') echo 'selected'; ?>>100% (Privzeto)</option>
                <option value="1.1" <?php if($uiScale == '1.1') echo 'selected'; ?>>110%</option>
                <option value="1.2" <?php if($uiScale == '1.2') echo 'selected'; ?>>120% (Večje)</option>
            </select>
        </div>

        <div class="actions" style="margin-top:2rem;">
            <button class="button" type="submit">Shrani</button>
        </div>
    </form>
</div>

<script>
(async function() {
    const dl = document.getElementById('arso-locs');
    if (!dl) return;

    try {
        const res = await fetch('/api/arso_proxy.php?action=locations');
        const json = await res.json();

        if (json.locations) {
            dl.innerHTML = json.locations.map(l => `<option value="${l.name}">${l.id}</option>`).join('');
        }
    } catch (e) {
        console.error('Failed to load ARSO locations', e);
    }
})();
</script>

<?php
render_footer();
