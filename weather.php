<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php';

use App\Settings;
use App\Database;
use App\Auth;

$pdo = Database::connect();
$user = Auth::user();
$isAdmin = $user && ($user['role'] === 'admin');

// Config
$resId = Settings::get($pdo, 'weather_resource_id', '');
$locCol = Settings::get($pdo, 'weather_location_col', '');
$timeCol = Settings::get($pdo, 'weather_time_col', '');

// Render
render_header('Vreme', $user, 'weather');
?>

<div class="view-page" style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h1 style="margin:0;">Vreme</h1>

        <?php if ($resId): ?>
        <div style="display:flex; gap:1rem; align-items:center;">
            <select id="location-select" class="form-control" style="min-width:200px; display:none;">
                <option value="">Izberi lokacijo...</option>
            </select>
            <button class="button small" id="btn-refresh">Osveži</button>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$resId): ?>
        <div class="card" style="text-align:center; padding:3rem;">
            <h3>Ni konfiguracije</h3>
            <p class="muted">Administrator mora nastaviti "Resource ID" v nastavitvah.</p>
            <?php if ($isAdmin): ?>
                <a href="/admin/settings.php" class="button" style="margin-top:1rem;">Pojdi v nastavitve</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Content -->
        <div id="weather-dashboard" style="display:none;">
            <!-- Current Stats -->
            <div class="grid-3" id="current-stats" style="margin-bottom:2rem;">
                <!-- Filled via JS -->
            </div>

            <!-- Chart -->
            <div class="card" style="margin-bottom:2rem; height:400px; position:relative;">
                <h3>Zgodovina meritev</h3>
                <div id="chart-container" style="width:100%; height:300px;">
                    <canvas id="weatherChart"></canvas>
                </div>
            </div>

            <!-- Table Link / Source -->
             <div style="text-align:right; font-size:0.8rem; color:var(--muted);">
                Vir podatkov: <a href="#" id="source-link" target="_blank" style="color:var(--accent);">CKAN Dataset</a>
            </div>
        </div>

        <div id="loading-state" style="text-align:center; padding:3rem;">
            Nalaganje podatkov...
        </div>

        <div id="error-state" class="card danger" style="display:none; text-align:center; padding:2rem;">
            <h3 style="color:#ff6b6b">Napaka</h3>
            <p id="error-msg"></p>
            <div id="error-actions" style="margin-top:1rem;"></div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Admin Debug -->
        <div style="margin-top:2rem;">
            <button class="button ghost small" onclick="document.getElementById('debug-container').style.display='block'">Debug (Admin)</button>
            <div id="debug-container" style="display:none; margin-top:1rem; background:#111; padding:1rem; border-radius:0.5rem; overflow:auto;">
                <pre id="debug-content" style="font-size:0.7rem; color:#0f0;"></pre>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function() {
    const CONFIG = {
        resId: "<?php echo htmlspecialchars($resId); ?>",
        locCol: "<?php echo htmlspecialchars($locCol); ?>",
        timeCol: "<?php echo htmlspecialchars($timeCol); ?>"
    };

    if (!CONFIG.resId) return;

    // Elements
    const els = {
        locSelect: document.getElementById('location-select'),
        dashboard: document.getElementById('weather-dashboard'),
        loading: document.getElementById('loading-state'),
        error: document.getElementById('error-state'),
        errorMsg: document.getElementById('error-msg'),
        errorActions: document.getElementById('error-actions'),
        debugContent: document.getElementById('debug-content'),
        stats: document.getElementById('current-stats'),
        btnRefresh: document.getElementById('btn-refresh'),
        sourceLink: document.getElementById('source-link')
    };

    let chartInstance = null;
    let savedLoc = localStorage.getItem('weather_loc_' + CONFIG.resId);

    // Init
    init();

    async function init() {
        els.btnRefresh.onclick = fetchData;
        els.locSelect.onchange = (e) => {
            savedLoc = e.target.value;
            localStorage.setItem('weather_loc_' + CONFIG.resId, savedLoc);
            fetchData();
        };

        try {
            // 1. Load Locations if column configured
            if (CONFIG.locCol) {
                const locs = await fetchLocations();
                if (locs.length > 0) {
                    renderLocations(locs);
                    els.locSelect.style.display = 'block';
                }
            }

            // 2. Load Data
            fetchData();

        } catch (e) {
            showError(e);
        }
    }

    async function fetchLocations() {
        // Fetch up to 1000 records and extract unique locations client-side
        // This avoids complex SQL calls and works for most typical weather station lists
        const url = `/api/ckan_proxy.php?action=datastore_search&resource_id=${CONFIG.resId}&fields=${CONFIG.locCol}&limit=1000`;
        const res = await fetch(url);
        const json = await res.json();

        if (!json.success) throw new Error(json.error_message || 'Error fetching locations');

        const unique = [...new Set(json.result.records.map(r => r[CONFIG.locCol]))].sort();
        return unique;
    }

    function renderLocations(locs) {
        els.locSelect.innerHTML = locs.map(l => `<option value="${l}">${l}</option>`).join('');
        if (savedLoc && locs.includes(savedLoc)) {
            els.locSelect.value = savedLoc;
        } else if (locs.length > 0) {
            savedLoc = locs[0];
            els.locSelect.value = savedLoc;
        }
    }

    async function fetchData() {
        showLoading();

        try {
            // Build Query
            let url = `/api/ckan_proxy.php?action=datastore_search&resource_id=${CONFIG.resId}&limit=100`;

            // Filter by location
            if (CONFIG.locCol && savedLoc) {
                const filters = {};
                filters[CONFIG.locCol] = savedLoc;
                url += `&filters=${encodeURIComponent(JSON.stringify(filters))}`;
            }

            // Sort by time desc
            // Use timeCol if set, else default to descending order (often implicit or _id)
            const sortCol = CONFIG.timeCol || '_id';
            url += `&sort=${encodeURIComponent(sortCol + ' desc')}`;

            const res = await fetch(url);
            const json = await res.json();

            // Update Debug View
            if (els.debugContent) {
                els.debugContent.textContent = JSON.stringify(json, null, 2);
            }

            if (!json.success) {
                if (json.error && json.error.__type == "Validation Error") {
                     // Check if resource exists but is not a datastore
                     checkIfResourceExists();
                     return;
                }
                throw new Error(json.error_message || 'CKAN Error');
            }

            const records = json.result.records;
            if (records.length === 0) {
                showError("Ni podatkov za to lokacijo.");
                return;
            }

            renderDashboard(records);

        } catch (e) {
            showError(e.message, e.debug || null);
        }
    }

    async function checkIfResourceExists() {
        // If datastore_search fails, try resource_show to get download link
        try {
            const url = `/api/ckan_proxy.php?action=resource_show&id=${CONFIG.resId}`;
            const res = await fetch(url);
            const json = await res.json();

            if (json.success) {
                const r = json.result;
                showError("Ta vir ni DataStore tabela.", null);
                els.errorActions.innerHTML = `
                    <p>Podatki niso na voljo v strukturirani obliki, lahko pa prenesete originalno datoteko:</p>
                    <a href="${r.url}" class="button" target="_blank">Prenesi ${r.format || 'datoteko'}</a>
                `;
            } else {
                showError("Vira ni mogoče najti.");
            }
        } catch (e) {
            showError("Napaka pri preverjanju vira.");
        }
    }

    function renderDashboard(records) {
        els.loading.style.display = 'none';
        els.error.style.display = 'none';
        els.dashboard.style.display = 'block';

        const latest = records[0];

        // 1. Heuristic Parsing for Stats
        const cards = [];
        const findVal = (keys) => {
            for (let k of keys) {
                const match = Object.keys(latest).find(key => key.toLowerCase().includes(k));
                if (match) return { key: match, val: latest[match] };
            }
            return null;
        };

        const temp = findVal(['temp', 't_2m', 'zrak']);
        if (temp) cards.push({ label: 'Temperatura', val: parseFloat(temp.val).toFixed(1) + ' °C', icon: 'thermostat' });

        const hum = findVal(['hum', 'vlag', 'rh']);
        if (hum) cards.push({ label: 'Vlaga', val: parseFloat(hum.val).toFixed(0) + ' %', icon: 'water_drop' });

        const wind = findVal(['wind', 'veter', 'hitrost']);
        if (wind) cards.push({ label: 'Veter', val: wind.val + ' m/s', icon: 'air' });

        const press = findVal(['press', 'tlak']);
        if (press) cards.push({ label: 'Tlak', val: parseFloat(press.val).toFixed(0) + ' hPa', icon: 'speed' });

        if (cards.length === 0) {
            Object.keys(latest).forEach(k => {
                if (k !== '_id' && k !== CONFIG.locCol && cards.length < 4) {
                    cards.push({ label: k, val: latest[k], icon: 'info' });
                }
            });
        }

        els.stats.innerHTML = cards.map(c => `
            <div class="card" style="text-align:center; padding:1.5rem;">
                <span class="material-icons" style="font-size:2rem; color:var(--accent); margin-bottom:0.5rem;">${c.icon}</span>
                <div style="font-size:0.9rem; color:var(--muted);">${c.label}</div>
                <div style="font-size:1.5rem; font-weight:bold;">${c.val}</div>
            </div>
        `).join('');

        // 2. Chart
        const history = [...records].reverse();
        const timeKey = CONFIG.timeCol || Object.keys(latest).find(k => k.toLowerCase().includes('time') || k.toLowerCase().includes('datum'));
        const dataKey = temp ? temp.key : (cards[0] ? cards[0].label : null);

        if (timeKey && dataKey) {
            const labels = history.map(r => {
                const d = new Date(r[timeKey]);
                return isNaN(d) ? r[timeKey] : d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            });
            const dataPoints = history.map(r => parseFloat(r[dataKey]));
            renderChart(labels, dataPoints, dataKey);
             // Updated Time
            els.sourceLink.innerText = 'Zadnja meritev: ' + (latest[timeKey] || '-');
        } else {
             document.getElementById('chart-container').innerHTML = '<p class="muted" style="text-align:center; padding:2rem;">Grafa ni mogoče izrisati (manjka časovni stolpec).</p>';
        }
    }

    function renderChart(labels, data, label) {
        const ctx = document.getElementById('weatherChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();
        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: label,
                    data: data,
                    borderColor: '#4b8bff',
                    backgroundColor: 'rgba(75, 139, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { display: true, grid: { display: false } },
                    y: { display: true, grid: { color:'rgba(255,255,255,0.1)' } }
                }
            }
        });
    }

    function showLoading() {
        els.dashboard.style.display = 'none';
        els.error.style.display = 'none';
        els.loading.style.display = 'block';
    }

    function showError(msg, debug = null) {
        els.loading.style.display = 'none';
        els.dashboard.style.display = 'none';
        els.error.style.display = 'block';
        els.errorMsg.textContent = msg;
        els.errorActions.innerHTML = ''; // Clear prev actions

        // Debug is shown in dedicated admin area updated in fetchData,
        // but if we have specific debug info from error, we can log it there too?
        // Prompt says "za admin naj UI prikaže surov odgovor CKAN...".
        // We handle that in fetchData via els.debugContent.
    }

})();
</script>

<?php
render_footer();
