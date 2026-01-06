<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php';

use App\Auth;

$user = Auth::user();
// Page accessible to public? Prompt didn't specify strict auth.
// Usually gallery pages are auth-only. "Ne spreminjaj obstoječih funkcij..."
// Layout implies sidebar is always visible. Layout also handles login redirect if needed usually?
// In `view.php`: `if (!$user) { header('Location: /login.php'); ... }`
// Let's assume weather is a utility for logged in users, similar to "Moje Slike".
if (!$user) {
    header('Location: /login.php');
    exit;
}

render_header('Vreme', $user, 'weather');
?>

<div class="weather-page" style="padding: 1rem; max-width: 1200px; margin: 0 auto;">

    <!-- Header / Controls -->
    <div class="card" style="margin-bottom: 2rem; padding: 1.5rem;">
        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between;">
            <h1 style="margin: 0; font-size: 1.5rem;">Vreme &amp; Radar</h1>

            <div class="controls" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                <button class="button" id="btn-geo" title="Uporabi mojo lokacijo">
                    <span class="material-icons">my_location</span>
                </button>

                <select id="location-preset" class="button ghost" style="padding-right: 2rem;">
                    <option value="" disabled selected>Izberi kraj...</option>
                    <option value="46.0569,14.5058">Ljubljana</option>
                    <option value="46.5547,15.6459">Maribor</option>
                    <option value="45.5481,13.7302">Koper</option>
                    <option value="46.2397,15.2677">Celje</option>
                    <option value="46.2389,14.3556">Kranj</option>
                </select>

                <div style="display: flex; gap: 0.5rem; background: rgba(255,255,255,0.05); padding: 0.25rem; border-radius: 0.5rem; border: 1px solid var(--border);">
                    <input type="number" id="inp-lat" placeholder="Lat" step="0.0001" style="width: 80px; background: transparent; border: none; color: white; padding: 0.25rem;">
                    <input type="number" id="inp-lon" placeholder="Lon" step="0.0001" style="width: 80px; background: transparent; border: none; color: white; padding: 0.25rem; border-left: 1px solid var(--border);">
                    <button class="button small" id="btn-update">Posodobi</button>
                </div>
            </div>
        </div>

        <div style="margin-top: 1rem; font-size: 0.85rem; color: var(--muted); display: flex; gap: 1rem; align-items: center;">
            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                <input type="checkbox" id="chk-autorefresh" checked>
                Samodejna osvežitev (3 min)
            </label>
            <span id="last-updated">Zadnja osvežitev: --:--:--</span>
        </div>
    </div>

    <!-- Error Container -->
    <div id="weather-error" class="card danger" style="display: none; padding: 1rem; margin-bottom: 2rem; border-left: 4px solid var(--danger);"></div>

    <!-- Dashboard Grid -->
    <div id="weather-content" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">

        <!-- Current Conditions (Radar) -->
        <div class="card">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                Trenutno (Radar)
            </h2>
            <div id="radar-data">
                <div class="skeleton-text" style="width: 60%;"></div>
                <div class="skeleton-text" style="width: 40%; margin-top: 0.5rem;"></div>
            </div>
        </div>

        <!-- Hail Probability -->
        <div class="card">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                Verjetnost Toče
            </h2>
            <div id="hail-data">
                <div class="skeleton-text" style="width: 50%;"></div>
                <div class="skeleton-block" style="height: 100px; margin-top: 1rem;"></div>
            </div>
        </div>

        <!-- Forecast -->
        <div class="card" style="grid-column: 1 / -1;">
            <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">
                Napoved (Model)
            </h2>
            <div id="forecast-chart" style="height: 60px; margin-bottom: 1rem; width: 100%;"></div>
            <div id="forecast-list" style="display: flex; overflow-x: auto; gap: 1rem; padding-bottom: 0.5rem;">
                <!-- Forecast Items -->
                <div class="skeleton-block" style="min-width: 80px; height: 120px;"></div>
                <div class="skeleton-block" style="min-width: 80px; height: 120px;"></div>
                <div class="skeleton-block" style="min-width: 80px; height: 120px;"></div>
                <div class="skeleton-block" style="min-width: 80px; height: 120px;"></div>
            </div>
        </div>

        <!-- Meta -->
        <div class="card" style="grid-column: 1 / -1; font-size: 0.85rem; color: var(--muted);">
             <div style="display:flex; justify-content: space-between; align-items: center;">
                 <span id="meta-info">Podatki se nalagajo...</span>
                 <button class="button ghost small" onclick="copyJson()">Kopiraj JSON</button>
             </div>
             <div style="margin-top: 0.5rem;">Vir: <a href="https://arso.gov.si" target="_blank" style="color: inherit; text-decoration: underline;">ARSO</a> (Agencija Republike Slovenije za okolje) prek opendata.si.</div>
        </div>

    </div>
</div>

<script src="/assets/js/weather.js"></script>

<style>
/* Local overrides or additions using CSS vars */
.skeleton-text {
    height: 1em;
    background: rgba(255,255,255,0.1);
    border-radius: 4px;
    animation: pulse 1.5s infinite;
}
.skeleton-block {
    background: rgba(255,255,255,0.1);
    border-radius: 8px;
    animation: pulse 1.5s infinite;
}
@keyframes pulse {
    0% { opacity: 0.6; }
    50% { opacity: 0.3; }
    100% { opacity: 0.6; }
}
.forecast-item {
    background: rgba(255,255,255,0.03);
    padding: 0.8rem;
    border-radius: 0.8rem;
    min-width: 90px;
    text-align: center;
    border: 1px solid transparent;
}
.forecast-item.rainy {
    border-color: rgba(75, 139, 255, 0.3);
    background: rgba(75, 139, 255, 0.05);
}
.badge {
    padding: 0.2rem 0.6rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: bold;
    display: inline-block;
}
.badge-low { background: #2ecc71; color: #fff; }
.badge-med { background: #f1c40f; color: #000; }
.badge-high { background: #e74c3c; color: #fff; }

.big-stat {
    font-size: 2.5rem;
    font-weight: 300;
    line-height: 1.2;
}
.sub-stat {
    color: var(--muted);
    font-size: 0.9rem;
}
</style>

<?php
render_footer();
