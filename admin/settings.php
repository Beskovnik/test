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
    $uiScale = (float)$_POST['ui_scale'];

    // Validation logic...
    if ($maxFiles > 100) $maxFiles = 100;
    if ($uiScale < 0.8) $uiScale = 0.8;
    if ($uiScale > 1.2) $uiScale = 1.2;

    Settings::set($pdo, 'max_image_gb', (string)$maxImageGb);
    Settings::set($pdo, 'max_video_gb', (string)$maxVideoGb);
    Settings::set($pdo, 'max_files_per_upload', (string)$maxFiles);

    // UI Settings
    Settings::set($pdo, 'accent_color', $_POST['accent_color']);
    Settings::set($pdo, 'ui_scale', (string)$uiScale);

    // CKAN Settings
    Settings::set($pdo, 'ckan_base_url', $_POST['ckan_base_url']);
    Settings::set($pdo, 'ckan_api_key', $_POST['ckan_api_key']);
    Settings::set($pdo, 'weather_resource_id', $_POST['weather_resource_id']);
    Settings::set($pdo, 'weather_location_col', $_POST['weather_location_col']);
    Settings::set($pdo, 'weather_time_col', $_POST['weather_time_col']);

    Audit::log($pdo, $user['id'], 'settings_update', 'Updated settings');

    header('Location: /admin/settings.php');
    exit;
}

$maxImageGb = Settings::get($pdo, 'max_image_gb', '5.0');
$maxVideoGb = Settings::get($pdo, 'max_video_gb', '5.0');
$maxFiles = Settings::get($pdo, 'max_files_per_upload', '100');
$accent = Settings::get($pdo, 'accent_color', '#4b8bff');
$uiScale = Settings::get($pdo, 'ui_scale', '1.0');

// CKAN Defaults
$ckanBase = Settings::get($pdo, 'ckan_base_url', 'https://podatki.gov.si');
$ckanKey = Settings::get($pdo, 'ckan_api_key', '');
$weatherResId = Settings::get($pdo, 'weather_resource_id', '');
$weatherLocCol = Settings::get($pdo, 'weather_location_col', '');
$weatherTimeCol = Settings::get($pdo, 'weather_time_col', '');

require __DIR__ . '/../includes/layout.php';
render_header('Nastavitve', $user, 'settings');
?>
<div class="admin-page">
    <h1>Nastavitve</h1>
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>

        <h3>Vreme / CKAN Integracija</h3>
        <p class="muted" style="margin-bottom:1rem;">Nastavitve za prikaz vremena iz CKAN DataStore (npr. podatki.gov.si).</p>

        <div class="grid-2">
            <div>
                <label>CKAN Base URL</label>
                <input type="text" name="ckan_base_url" value="<?php echo htmlspecialchars($ckanBase); ?>" placeholder="https://podatki.gov.si">
            </div>
            <div>
                <label>CKAN API Key (Opcijsko)</label>
                <input type="password" name="ckan_api_key" value="<?php echo htmlspecialchars($ckanKey); ?>" placeholder="Pusti prazno za javni dostop">
            </div>
        </div>

        <div class="grid-2" style="margin-top:1rem; align-items:end;">
            <div>
                <label>Weather Resource ID</label>
                <input type="text" id="inp-res-id" name="weather_resource_id" value="<?php echo htmlspecialchars($weatherResId); ?>" placeholder="UUID vira">
            </div>
            <div>
                <button type="button" class="button ghost" onclick="openCkanSearch()">🔍 Poišči Vir</button>
            </div>
        </div>

        <div class="grid-2" style="margin-top:1rem;">
             <div>
                <label>Stolpec za Lokacijo (Opcijsko)</label>
                <input type="text" name="weather_location_col" value="<?php echo htmlspecialchars($weatherLocCol); ?>" placeholder="npr. postaja">
            </div>
            <div>
                <label>Stolpec za Čas (Opcijsko)</label>
                <input type="text" name="weather_time_col" value="<?php echo htmlspecialchars($weatherTimeCol); ?>" placeholder="npr. datum_ur">
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

<!-- Simple CKAN Search Modal -->
<div id="ckan-modal" class="modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
    <div class="modal-content card" style="width:600px; max-width:90%; max-height:80vh; display:flex; flex-direction:column;">
        <div style="display:flex; justify-content:space-between; margin-bottom:1rem;">
            <h3>Išči Dataset</h3>
            <button type="button" onclick="closeCkanSearch()" style="background:none; border:none; color:white; font-size:1.5rem;">&times;</button>
        </div>
        <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
            <input type="text" id="ckan-q" placeholder="Ključna beseda (npr. vreme, arso)..." style="flex:1;">
            <button type="button" class="button" onclick="searchCkan()">Išči</button>
        </div>
        <div id="ckan-results" style="overflow-y:auto; flex:1;"></div>
    </div>
</div>

<script>
function openCkanSearch() {
    document.getElementById('ckan-modal').style.display = 'flex';
}
function closeCkanSearch() {
    document.getElementById('ckan-modal').style.display = 'none';
}
async function searchCkan() {
    const q = document.getElementById('ckan-q').value;
    const list = document.getElementById('ckan-results');
    list.innerHTML = 'Nalaganje...';

    try {
        const res = await fetch('/api/ckan_proxy.php?action=package_search&q=' + encodeURIComponent(q));
        const json = await res.json();

        if (!json.success) throw new Error(json.error_message || 'Napaka');

        list.innerHTML = '';
        if (json.result.results.length === 0) {
            list.innerHTML = '<p>Ni rezultatov.</p>';
            return;
        }

        json.result.results.forEach(pkg => {
            const item = document.createElement('div');
            item.style.padding = '1rem';
            item.style.borderBottom = '1px solid var(--border)';
            item.innerHTML = `
                <div style="font-weight:bold;">${pkg.title}</div>
                <div style="font-size:0.8rem; color:var(--muted); margin-bottom:0.5rem;">${pkg.organization ? pkg.organization.title : ''}</div>
                <div class="resources" style="display:flex; flex-wrap:wrap; gap:0.5rem;"></div>
            `;

            const resContainer = item.querySelector('.resources');
            pkg.resources.forEach(r => {
                const btn = document.createElement('button');
                btn.className = 'button small ghost';
                btn.type = 'button';
                btn.innerText = r.name || r.format || 'Resource';
                btn.title = r.id;
                btn.onclick = () => {
                    document.getElementById('inp-res-id').value = r.id;
                    closeCkanSearch();
                };
                if (r.datastore_active) {
                    btn.style.borderColor = 'var(--accent)';
                    btn.innerText += ' (DataStore)';
                }
                resContainer.appendChild(btn);
            });

            list.appendChild(item);
        });

    } catch (e) {
        list.innerText = 'Napaka: ' + e.message;
    }
}
</script>

<?php
render_footer();
