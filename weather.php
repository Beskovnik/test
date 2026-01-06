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

<style>
    /* Main Layout */
    .weather-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px;
        width: 100%;
        box-sizing: border-box;
    }

    /* Header */
    .weather-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .weather-title h1 {
        margin: 0;
        font-size: 2rem;
        line-height: 1.2;
    }

    .weather-location {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        margin-top: 4px;
    }

    /* Sections */
    .weather-section {
        margin-bottom: 32px;
    }

    /* Cards - Unified Style */
    .weather-card {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: flex;
        flex-direction: column;
    }

    .weather-card.no-padding {
        padding: 0;
        overflow: hidden;
    }

    .weather-card-header {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 16px;
    }

    /* KPI Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr); /* Default/Desktop: 2x2 */
        gap: 16px;
    }

    .kpi-card-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        height: 100%;
        min-height: 140px; /* Ensure equal height visual */
    }

    .kpi-icon {
        font-size: 2.5rem;
        margin-bottom: 12px;
        color: var(--accent, #4b8bff);
    }

    .kpi-label {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kpi-value {
        font-size: 1.75rem;
        font-weight: 700;
        color: #fff;
    }

    /* Chart */
    .chart-wrapper {
        width: 100%;
        height: 350px;
        position: relative;
    }

    /* Table */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .weather-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 600px;
    }

    .weather-table th {
        text-align: left;
        padding: 16px 20px;
        background: rgba(255, 255, 255, 0.05);
        font-weight: 600;
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.9);
    }

    .weather-table td {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        font-size: 0.95rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .weather-table tr:last-child td {
        border-bottom: none;
    }

    /* Responsive Breakpoints */
    /* Mobile < 640px */
    @media (max-width: 639px) {
        .kpi-grid {
            grid-template-columns: 1fr; /* 1x4 */
        }
    }

    /* Tablet 640px - 1024px */
    @media (min-width: 640px) and (max-width: 1023px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr); /* 2x2 */
        }
    }

    /* Desktop >= 1024px */
    @media (min-width: 1024px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr); /* 2x2 */
        }
    }
</style>

<div class="weather-container">

    <!-- 1. Header & Location -->
    <div class="weather-header">
        <div class="weather-title">
            <h1>Vreme</h1>
            <?php if ($arsoLoc): ?>
                <div class="weather-location">
                    Lokacija: <strong><?php echo htmlspecialchars($arsoLoc); ?></strong>
                    <?php if ($arsoStation): ?> (Postaja: <?php echo htmlspecialchars($arsoStation); ?>)<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <button class="button small" id="btn-refresh">Osveži</button>
    </div>

    <?php if (!$arsoLoc): ?>
        <!-- No Config State -->
        <div class="weather-card" style="text-align:center; padding:3rem; align-items:center;">
            <h3>Ni konfiguracije</h3>
            <p style="color:rgba(255,255,255,0.6); margin-bottom:1.5rem;">Administrator mora nastaviti lokacijo (ARSO) v nastavitvah.</p>
            <?php if ($isAdmin): ?>
                <a href="/admin/settings.php" class="button">Pojdi v nastavitve</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- Loading State -->
        <div id="loading-state" style="text-align:center; padding:5rem;">
            <div class="spinner" style="margin-bottom:1rem;"></div>
            <div style="color:rgba(255,255,255,0.6);">Nalaganje podatkov...</div>
        </div>

        <!-- Error State -->
        <div id="error-state" class="weather-card" style="display:none; text-align:center; align-items:center; border-color:#ff6b6b;">
            <h3 style="color:#ff6b6b">Napaka</h3>
            <p id="error-msg" style="color:rgba(255,255,255,0.8);"></p>
        </div>

        <!-- Content Dashboard -->
        <div id="weather-dashboard" style="display:none;">

            <!-- SECTION A: Current Stats -->
            <div class="weather-section">
                <div id="current-stats" class="kpi-grid">
                    <!-- Injected by JS -->
                </div>
            </div>

            <!-- SECTION B: Forecast Chart -->
            <div class="weather-section">
                <div class="weather-card">
                    <div class="weather-card-header">Napoved (48h)</div>
                    <div class="chart-wrapper">
                        <canvas id="weatherChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- SECTION C: Detailed Table -->
            <div class="weather-section">
                <div class="weather-card no-padding">
                    <div style="padding: 20px 20px 0 20px;">
                        <div class="weather-card-header" style="margin-bottom:10px;">Podrobna Napoved</div>
                    </div>
                    <div class="table-responsive">
                        <table class="weather-table">
                            <thead>
                                <tr>
                                    <th>Čas</th>
                                    <th>Stanje</th>
                                    <th>Temp</th>
                                    <th>Veter</th>
                                    <th>Padavine</th>
                                </tr>
                            </thead>
                            <tbody id="forecast-table-body">
                                <!-- Injected by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div style="text-align:right; font-size:0.8rem; color:rgba(255,255,255,0.5); margin-top:0.5rem;">
                    Vir podatkov: <a href="https://vreme.arso.gov.si/" target="_blank" style="color:var(--accent);">ARSO</a>
                </div>
            </div>

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
            const res = await fetch(`/api/arso_proxy.php?action=data&location=${encodeURIComponent(CONFIG.loc)}`);
            const json = await res.json();

            if (!json || json.error) throw new Error(json.error || 'Napaka pri pridobivanju podatkov.');

            let data = json;
            if (data.forecast3h) data = data.forecast3h;
            if (data.forecast1h) data = data.forecast1h;

            if (data.features && data.features.length > 0) {
                 render(data.features[0].properties);
            } else {
                 console.error('Invalid Data:', json);
                 throw new Error('Nepravilna struktura podatkov (ARSO).');
            }

        } catch (e) {
            showError(e.message);
        }
    }

    function render(props) {
        els.loading.style.display = 'none';
        els.dashboard.style.display = 'block';

        // 1. Current Stats
        const today = props.days[0];
        const current = today.timeline[0];

        const temp = current.t;
        const wind = current.ff_val;
        const press = current.msl;
        const rain = current.tp_acc;
        // const icon = current.sky_icon_url || '';

        const cards = [
            { label: 'Temperatura', val: (temp !== undefined ? temp + ' °C' : '-'), icon: 'thermostat' },
            { label: 'Veter', val: (wind !== undefined ? wind + ' m/s' : '-'), icon: 'air' },
            { label: 'Tlak', val: (press !== undefined ? press + ' hPa' : '-'), icon: 'speed' },
            { label: 'Padavine', val: (rain !== undefined ? rain + ' mm' : '0 mm'), icon: 'water_drop' }
        ];

        // UPDATED: Using new card structure and classes
        els.stats.innerHTML = cards.map(c => `
            <div class="weather-card">
                <div class="kpi-card-content">
                    <span class="material-icons kpi-icon">${c.icon}</span>
                    <div class="kpi-label">${c.label}</div>
                    <div class="kpi-value">${c.val}</div>
                </div>
            </div>
        `).join('');

        // 2. Chart
        let timeline = [];
        if (props.days[0]) timeline = timeline.concat(props.days[0].timeline);
        if (props.days[1]) timeline = timeline.concat(props.days[1].timeline);

        timeline = timeline.slice(0, 48);

        const labels = timeline.map(t => {
            const d = new Date(t.valid);
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
             const state = t.nn_shortText || t.clouds_shortText || '-';

             return `
                <tr>
                    <td>
                        <div style="font-weight:bold; color:#fff;">${timeStr}</div>
                        <div style="font-size:0.8rem; color:rgba(255,255,255,0.5);">${dateStr}</div>
                    </td>
                    <td>${state}</td>
                    <td>${t.t !== undefined ? t.t + ' °C' : '-'}</td>
                    <td>${t.ff_val !== undefined ? t.ff_val + ' m/s' : '-'}</td>
                    <td>${t.tp_acc !== undefined ? t.tp_acc + ' mm' : '-'}</td>
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
                    x: {
                        display: true,
                        grid: { display: false },
                        ticks: { color: 'rgba(255,255,255,0.6)' }
                    },
                    y: {
                        display: true,
                        position: 'left',
                        grid: { color:'rgba(255,255,255,0.1)' },
                        ticks: { color: 'rgba(255,255,255,0.6)' }
                    },
                    y1: {
                        display: true,
                        position: 'right',
                        grid: { display: false },
                        min: 0,
                        ticks: { display: false } // Hide ticks for aesthetics if desired, but good to keep
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: 'rgba(255,255,255,0.8)' }
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
        els.error.style.display = 'flex'; // Changed to flex to use align-items
        els.errorMsg.textContent = msg;
    }

})();
</script>

<?php
render_footer();
