<?php
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/app/Bootstrap.php';

use App\Auth;
use App\Database;

$user = Auth::requireLogin();
$pdo = Database::connect();

$shareId = $_GET['id'] ?? null;
if (!$shareId) {
    redirect('/my-shares.php');
}

// Check Access
$stmt = $pdo->prepare("SELECT * FROM shares WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$shareId, $user['id']]);
$share = $stmt->fetch();

if (!$share) {
    echo "Dostop zavrnjen.";
    exit;
}

render_header('Analitika: ' . $share['title'], $user, 'myshares');
?>
<div class="container" style="padding: 2rem; max-width: 1000px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h1 style="margin:0;">Analitika: <?php echo htmlspecialchars($share['title']); ?></h1>
        <a href="/my-shares.php" class="button ghost">Nazaj</a>
    </div>

    <!-- Summary Cards -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem; margin-bottom:2rem;">
        <div class="card" style="padding:1.5rem; text-align:center;">
            <div style="font-size:2rem; font-weight:bold; color:var(--accent);"><?php echo $share['views_total']; ?></div>
            <div style="color:var(--muted);">Ogledov</div>
        </div>
        <div class="card" style="padding:1.5rem; text-align:center;">
            <div style="font-size:2rem; font-weight:bold; color:var(--accent);"><?php echo $share['downloads_total']; ?></div>
            <div style="color:var(--muted);">Prenosov</div>
        </div>
        <div class="card" style="padding:1.5rem; text-align:center;">
             <div style="font-size:2rem; font-weight:bold; color:var(--text);"><?php echo $share['item_count'] ?? '?'; ?></div>
             <div style="color:var(--muted);">Elementov</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="card" style="padding:2rem; margin-bottom:2rem;">
        <h3 style="margin-top:0;">Aktivnost (zadnjih 30 dni)</h3>
        <div id="chartContainer" style="height: 300px; display:flex; align-items:flex-end; gap:4px; padding-top:2rem;">
            <div class="loading-spinner">Nalaganje grafa...</div>
        </div>
    </div>

    <!-- Top Items -->
    <div class="card" style="padding:2rem;">
        <h3 style="margin-top:0;">Najbolj prenešeno</h3>
        <div id="topItemsList">
            <div class="loading-spinner">Nalaganje...</div>
        </div>
    </div>
</div>

<script>
const SHARE_ID = <?php echo $shareId; ?>;

async function loadAnalytics() {
    // Load Timeseries
    try {
        const res = await fetch(`/api/share/analytics.php?share_id=${SHARE_ID}&type=timeseries`);
        const json = await res.json();
        if (json.success) {
            renderChart(json.data);
        }
    } catch(e) { console.error(e); }

    // Load Top Items
    try {
        const res = await fetch(`/api/share/analytics.php?share_id=${SHARE_ID}&type=top_items`);
        const json = await res.json();
        if (json.success) {
            renderTopItems(json.data);
        }
    } catch(e) { console.error(e); }
}

function renderChart(data) {
    const container = document.getElementById('chartContainer');
    container.innerHTML = '';

    // Find max for scaling
    let max = 0;
    Object.values(data).forEach(d => {
        max = Math.max(max, d.views + d.downloads);
    });
    if(max === 0) max = 1;

    Object.keys(data).sort().forEach(date => {
        const day = data[date];
        const total = day.views + day.downloads;
        const h = (total / max) * 100; // percent height

        const bar = document.createElement('div');
        bar.style.flex = '1';
        bar.style.background = 'rgba(255,255,255,0.1)';
        bar.style.height = '100%';
        bar.style.display = 'flex';
        bar.style.flexDirection = 'column-reverse';
        bar.style.position = 'relative';
        bar.title = `${date}: ${day.views} views, ${day.downloads} downloads`;

        // Bar content
        const fill = document.createElement('div');
        fill.style.height = h + '%';
        fill.style.background = 'var(--accent)';
        fill.style.minHeight = total > 0 ? '4px' : '0';
        fill.style.borderRadius = '2px 2px 0 0';

        bar.appendChild(fill);
        container.appendChild(bar);
    });
}

function renderTopItems(items) {
    const container = document.getElementById('topItemsList');
    container.innerHTML = '';

    if (items.length === 0) {
        container.innerHTML = '<p class="muted">Ni podatkov.</p>';
        return;
    }

    const table = document.createElement('table');
    table.style.width = '100%';
    table.innerHTML = `
        <thead>
            <tr style="text-align:left; border-bottom:1px solid var(--border)">
                <th style="padding:0.5rem">Element</th>
                <th style="padding:0.5rem">Prenosov</th>
            </tr>
        </thead>
        <tbody>
            ${items.map(i => {
                // Escape title
                const safeTitle = i.title ? i.title.replace(/&/g, "&amp;")
                        .replace(/</g, "&lt;")
                        .replace(/>/g, "&gt;")
                        .replace(/"/g, "&quot;")
                        .replace(/'/g, "&#039;") : 'Untitled';

                return `
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05)">
                    <td style="padding:0.5rem; display:flex; align-items:center; gap:1rem;">
                        <img src="/${i.thumb_path || 'assets/img/placeholder.svg'}" style="width:40px; height:40px; object-fit:cover; border-radius:4px;">
                        <span>${safeTitle}</span>
                    </td>
                    <td style="padding:0.5rem">${i.downloads}</td>
                </tr>
            `}).join('')}
        </tbody>
    `;
    container.appendChild(table);
}

loadAnalytics();
</script>
<?php render_footer(); ?>
