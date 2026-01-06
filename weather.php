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
$arsoLoc = Settings::get($pdo, 'weather_arso_location', '');
$arsoStation = Settings::get($pdo, 'weather_arso_station_id', '');

// Render
render_header('Vreme', $user, 'weather');
?>

<div class="view-page" style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <div>
            <h1 style="margin:0;">Vreme</h1>
            <?php if ($arsoLoc): ?>
                <div style="color:var(--muted); font-size:0.9rem; margin-top:0.5rem;">
                    Lokacija: <strong><?php echo htmlspecialchars($arsoLoc); ?></strong>
                    <?php if ($arsoStation): ?> (Postaja: <?php echo htmlspecialchars($arsoStation); ?>)<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <button class="button small" id="btn-refresh">Osveži</button>
    </div>

    <?php if (!$arsoLoc): ?>
        <div class="card" style="text-align:center; padding:3rem;">
            <h3>Ni konfiguracije</h3>
            <p class="muted">Administrator mora nastaviti lokacijo (ARSO) v nastavitvah.</p>
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
            <div class="card" style="margin-bottom:2rem; padding:1.5rem;">
                <h3 style="margin-bottom:1rem;">Napoved (48h)</h3>
                <div style="width:100%; height:300px;">
                    <canvas id="weatherChart"></canvas>
                </div>
            </div>

            <!-- Forecast Table -->
            <div class="card" style="padding:0; overflow:hidden;">
                <h3 style="padding:1.5rem; margin:0; border-bottom:1px solid var(--border);">Podrobna Napoved</h3>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%; text-align:left;">
                        <thead>
                            <tr style="background:rgba(255,255,255,0.05);">
                                <th style="padding:1rem;">Čas</th>
                                <th style="padding:1rem;">Stanje</th>
                                <th style="padding:1rem;">Temp</th>
                                <th style="padding:1rem;">Veter</th>
                                <th style="padding:1rem;">Padavine</th>
                            </tr>
                        </thead>
                        <tbody id="forecast-table-body">
                            <!-- JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="text-align:right; font-size:0.8rem; color:var(--muted); margin-top:1rem;">
                Vir podatkov: <a href="https://vreme.arso.gov.si/" target="_blank" style="color:var(--accent);">ARSO</a>
            </div>
        </div>

        <div id="loading-state" style="text-align:center; padding:5rem;">
            <div class="spinner" style="margin-bottom:1rem;"></div>
            Nalaganje podatkov...
        </div>

        <div id="error-state" class="card danger" style="display:none; text-align:center; padding:2rem;">
            <h3 style="color:#ff6b6b">Napaka</h3>
            <p id="error-msg"></p>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
(function() {
    const CONFIG = {
        loc: "<?php echo htmlspecialchars($arsoLoc); ?>",
        station: "<?php echo htmlspecialchars($arsoStation); ?>"
    };

    if (!CONFIG.loc) return;

    // Elements
    const els = {
        dashboard: document.getElementById('weather-dashboard'),
        loading: document.getElementById('loading-state'),
        error: document.getElementById('error-state'),
        errorMsg: document.getElementById('error-msg'),
        stats: document.getElementById('current-stats'),
        table: document.getElementById('forecast-table-body'),
        btnRefresh: document.getElementById('btn-refresh')
    };

    let chartInstance = null;

    init();

    function init() {
        els.btnRefresh.onclick = fetchData;
        fetchData();
    }

    async function fetchData() {
        showLoading();
        try {
            // 1. Fetch Forecast/Current (JSON)
            const res = await fetch(`/api/arso_proxy.php?action=data&location=${encodeURIComponent(CONFIG.loc)}`);
            const json = await res.json();

            if (!json || json.error) throw new Error(json.error || 'Napaka pri pridobivanju podatkov.');

            // Structure check: ARSO returns { observation: {...}, forecast: {...} } or similar structure?
            // Wait, looking at ARSO API docs or typical response:
            // It usually has 'features' array if it's GeoJSON-like, or specific keys.
            // Let's assume the proxy returns what ARSO returns.
            // ARSO /api/1.0/location/?location=Ljubljana returns object with 'features' where features[0].properties contains 'days'.

            // If proxy passed it through directly:
            let data = json;
            // Handle ARSO wrapper (sometimes inside forecast3h or similar)
            if (data.forecast3h) data = data.forecast3h;
            if (data.forecast1h) data = data.forecast1h; // Fallback if structure varies

            if (data.features && data.features.length > 0) {
                 render(data.features[0].properties);
            } else {
                 console.error('Invalid Data:', json);
                 throw new Error('Nepravilna struktura podatkov (ARSO). Prejeto: ' + JSON.stringify(json).substring(0, 200));
            }

        } catch (e) {
            showError(e.message);
        }
    }

    function render(props) {
        els.loading.style.display = 'none';
        els.dashboard.style.display = 'block';

        // 1. Current Stats (from latest timeline item of today)
        // properties.days[0].timeline[0] is usually current or close to it
        const today = props.days[0];
        const current = today.timeline[0]; // The first one is usually the most relevant "current" forecast interval

        // Try to find if we have observation data merged?
        // ARSO API 'location' endpoint gives forecast.
        // Actual CURRENT observation comes from a different endpoint usually,
        // but let's stick to the forecast "now" which is good enough for "Vreme".

        const temp = current.t;
        const wind = current.ff_val;
        const press = current.msl;
        const rain = current.tp_acc; // precipitation
        const icon = current.sky_icon_url || ''; // ARSO doesn't give full URL usually, just ID?
        // Actually ARSO returns `pictogram_code` or similar.
        // Let's rely on standard fields.

        const cards = [
            { label: 'Temperatura', val: (temp !== undefined ? temp + ' °C' : '-'), icon: 'thermostat' },
            { label: 'Veter', val: (wind !== undefined ? wind + ' m/s' : '-'), icon: 'air' },
            { label: 'Tlak', val: (press !== undefined ? press + ' hPa' : '-'), icon: 'speed' },
            { label: 'Padavine', val: (rain !== undefined ? rain + ' mm' : '0 mm'), icon: 'water_drop' }
        ];

        els.stats.innerHTML = cards.map(c => `
            <div class="card" style="text-align:center; padding:1.5rem;">
                <span class="material-icons" style="font-size:2rem; color:var(--accent); margin-bottom:0.5rem;">${c.icon}</span>
                <div style="font-size:0.9rem; color:var(--muted);">${c.label}</div>
                <div style="font-size:1.5rem; font-weight:bold;">${c.val}</div>
            </div>
        `).join('');

        // 2. Chart (48h - join day 0 and day 1 timelines)
        let timeline = [];
        if (props.days[0]) timeline = timeline.concat(props.days[0].timeline);
        if (props.days[1]) timeline = timeline.concat(props.days[1].timeline);

        // Sort by time just in case
        // timeline.sort((a,b) => new Date(a.valid) - new Date(b.valid));

        // Take next 24-48 points (assuming hourly)
        timeline = timeline.slice(0, 48);

        const labels = timeline.map(t => {
            const d = new Date(t.valid); // ISO string
            return d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        });
        const temps = timeline.map(t => t.t);
        const rains = timeline.map(t => t.tp_acc || 0);

        renderChart(labels, temps, rains);

        // 3. Table
        els.table.innerHTML = timeline.slice(0, 24).map(t => {
             const d = new Date(t.valid);
             const timeStr = d.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
             const dateStr = d.toLocaleDateString();
             // Simple mapping for state if available
             const state = t.nn_shortText || t.clouds_shortText || '-';

             return `
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <td style="padding:0.75rem 1rem;">
                        <div style="font-weight:bold;">${timeStr}</div>
                        <div style="font-size:0.8rem; color:var(--muted);">${dateStr}</div>
                    </td>
                    <td style="padding:0.75rem 1rem;">${state}</td>
                    <td style="padding:0.75rem 1rem;">${t.t !== undefined ? t.t + ' °C' : '-'}</td>
                    <td style="padding:0.75rem 1rem;">${t.ff_val !== undefined ? t.ff_val + ' m/s' : '-'}</td>
                    <td style="padding:0.75rem 1rem;">${t.tp_acc !== undefined ? t.tp_acc + ' mm' : '-'}</td>
                </tr>
             `;
        }).join('');
    }

    function renderChart(labels, temps, rains) {
        const ctx = document.getElementById('weatherChart').getContext('2d');
        if (chartInstance) chartInstance.destroy();

        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Temperatura (°C)',
                        data: temps,
                        borderColor: '#4b8bff',
                        backgroundColor: 'rgba(75, 139, 255, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        type: 'bar',
                        label: 'Padavine (mm)',
                        data: rains,
                        backgroundColor: 'rgba(46, 204, 113, 0.3)',
                        borderColor: 'rgba(46, 204, 113, 0.8)',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: { display: true, grid: { display: false } },
                    y: {
                        display: true,
                        position: 'left',
                        grid: { color:'rgba(255,255,255,0.1)' }
                    },
                    y1: {
                        display: true,
                        position: 'right',
                        grid: { display: false },
                        min: 0
                    }
                }
            }
        });
    }

    function showLoading() {
        els.dashboard.style.display = 'none';
        els.error.style.display = 'none';
        els.loading.style.display = 'block';
    }

    function showError(msg) {
        els.loading.style.display = 'none';
        els.dashboard.style.display = 'none';
        els.error.style.display = 'block';
        els.errorMsg.textContent = msg;
    }

})();
</script>

<?php
render_footer();
