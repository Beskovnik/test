<?php
require __DIR__ . '/app/Bootstrap.php';

use App\Database;
use App\Settings;

// 1. Validate Token
$token = $_GET['token'] ?? '';
if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(404);
    die("Invalid link.");
}

$pdo = Database::connect();
$stmt = $pdo->prepare("SELECT * FROM shares WHERE token = ? AND is_active = 1");
$stmt->execute([$token]);
$share = $stmt->fetch();

if (!$share) {
    http_response_code(404);
    die("Link does not exist or has expired.");
}

// 2. Track View (Server-side)
// Privacy-friendly: Hash IP + Salt.
// Debounce: Max 1 view per 10 min per session.
// session_start();
$sessionId = session_id(); // Use PHP session ID as visitor identifier
$now = time();

// Check last view time for this session (in session storage, or DB check)
// For DB check, we look at share_events
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$dateSalt = date('Y-m-d'); // Rotate salt daily for privacy
$ipHash = hash('sha256', $ip . $dateSalt . 'SHARE_SALT');

$shouldTrack = true;
if (isset($_SESSION['viewed_shares'][$token])) {
    if ($now - $_SESSION['viewed_shares'][$token] < 600) { // 10 min
        $shouldTrack = false;
    }
}

if ($shouldTrack) {
    $_SESSION['viewed_shares'][$token] = $now;

    // Insert Event
    try {
        $stmtEvent = $pdo->prepare("INSERT INTO share_events (share_id, event_type, occurred_at, ip_hash, session_id, user_agent_type) VALUES (?, 'view', ?, ?, ?, ?)");
        // Simple UA detection
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $uaType = 'desktop';
        if (preg_match('/(android|iphone|ipad|mobile)/i', $ua)) $uaType = 'mobile';

        $stmtEvent->execute([$share['id'], $now, $ipHash, $sessionId, $uaType]);

        // Update Aggregates
        $pdo->prepare("UPDATE shares SET views_total = views_total + 1, last_view_at = ? WHERE id = ?")->execute([$now, $share['id']]);
    } catch (Exception $e) {
        // Ignore tracking errors to not break page
        error_log("Tracking error: " . $e->getMessage());
    }
}

// 3. Render Page
// Using a minimal, modern layout dedicated for sharing.
// Re-using some CSS from app.css but maybe simpler structure.

$accentColor = Settings::get($pdo, 'accent_color', '#4b8bff');
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($share['title']); ?> - Shared</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        :root { --accent: <?php echo htmlspecialchars($accentColor); ?>; }
        body { background: #0f0f13; color: white; margin: 0; padding: 0; min-height: 100vh; }
        .share-header {
            padding: 2rem;
            text-align: center;
            background: linear-gradient(to bottom, rgba(0,0,0,0.8), transparent);
        }
        .share-title { font-size: 2rem; margin: 0; font-weight: 300; }
        .share-meta { color: var(--muted); margin-top: 0.5rem; font-size: 0.9rem; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        .item-card {
            background: var(--panel);
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid var(--border);
            aspect-ratio: 1;
            position: relative;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .item-card:hover { transform: translateY(-2px); border-color: var(--accent); }
        .item-card img { width: 100%; height: 100%; object-fit: cover; }
        .item-type {
            position: absolute; top: 0.5rem; right: 0.5rem;
            background: rgba(0,0,0,0.7); padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem;
        }
        /* Modal Viewer */
        .modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.95); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .modal.open { opacity: 1; pointer-events: auto; }
        .modal-content { max-width: 90%; max-height: 90%; position: relative; }
        .modal-content img, .modal-content video { max-width: 100%; max-height: 90vh; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
        .close-btn { position: absolute; top: 20px; right: 30px; font-size: 3rem; color: white; cursor: pointer; z-index: 10001;}
        .download-btn {
            position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%);
            background: var(--accent); color: white; padding: 0.8rem 2rem; border-radius: 2rem;
            text-decoration: none; font-weight: bold; display: flex; align-items: center; gap: 0.5rem;
            z-index: 10001;
        }
    </style>
</head>
<body>

<header class="share-header">
    <h1 class="share-title"><?php echo htmlspecialchars($share['title']); ?></h1>
    <div class="share-meta">
        Deljeno <?php echo date('d.m.Y', (int)$share['created_at']); ?>
    </div>
</header>

<div id="grid-container" class="grid">
    <div class="loading-spinner" style="text-align:center; grid-column: 1/-1;">Nalaganje...</div>
</div>

<!-- Viewer Modal -->
<div class="modal" id="viewerModal">
    <span class="close-btn" onclick="closeModal()">&times;</span>
    <div class="modal-content" id="modalContent">
        <!-- Media injected here -->
    </div>
    <a href="#" id="downloadLink" class="download-btn" target="_blank">
        <span class="material-icons">download</span> Prenesi
    </a>
</div>

<script>
const TOKEN = "<?php echo $token; ?>";

async function loadItems() {
    try {
        const res = await fetch(`/api/share/get.php?token=${TOKEN}`);
        const data = await res.json();

        if (!data.success) {
            document.body.innerHTML = `<div style='padding:4rem;text-align:center'>Error: ${data.error}</div>`;
            return;
        }

        const container = document.getElementById('grid-container');
        container.innerHTML = '';

        if (data.items.length === 0) {
            container.innerHTML = '<div style="text-align:center;grid-column:1/-1;">Zbirka je prazna.</div>';
            return;
        }

        data.items.forEach(item => {
            const el = document.createElement('div');
            el.className = 'item-card';

            // Image logic
            let thumb = item.thumb_path ? '/' + item.thumb_path : '/assets/img/placeholder.svg';
            if(item.type === 'video') {
                // Video thumb is jpg
            } else {
                 // Use thumb.php for sizing? Or pre-generated.
                 // The API returns DB paths.
                 // Let's use thumb.php for consistent sizing if needed, or raw path
                 if(!item.thumb_path) thumb = `/thumb.php?src=/${encodeURIComponent(item.file_path)}&w=300&h=300&fit=cover`;
                 else thumb = '/' + item.thumb_path;
            }

            el.innerHTML = `
                <img src="${thumb}" loading="lazy">
                ${item.type === 'video' ? '<span class="item-type">Video</span>' : ''}
            `;
            el.querySelector('img').alt = item.title; // Safe assignment
            el.onclick = () => openModal(item);
            container.appendChild(el);
        });

    } catch(e) {
        console.error(e);
    }
}

function openModal(item) {
    const modal = document.getElementById('viewerModal');
    const content = document.getElementById('modalContent');
    const dlBtn = document.getElementById('downloadLink');

    content.innerHTML = '';

    let mediaHtml = '';
    // Prefer preview path for large view
    const src = item.preview_path ? '/' + item.preview_path : '/' + item.file_path;
    const original = '/' + item.file_path;

    if (item.type === 'video') {
        mediaHtml = `<video src="${original}" controls autoplay style="max-height:80vh"></video>`;
    } else {
        mediaHtml = `<img src="${src}" style="max-height:80vh">`;
    }

    content.innerHTML = mediaHtml;

    // Setup Download Link (tracked)
    dlBtn.href = `/api/download.php?token=${TOKEN}&media_id=${item.id}`;

    modal.classList.add('open');
}

function closeModal() {
    document.getElementById('viewerModal').classList.remove('open');
    document.getElementById('modalContent').innerHTML = ''; // Stop video
}

loadItems();
</script>

</body>
</html>
