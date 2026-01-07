<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php'; // Required for render_header

use App\Auth;
use App\Database;

$user = Auth::user();
$pdo = Database::connect();

// Params
$typeFilter = $_GET['type'] ?? null;
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

// Build Query
$viewMode = $_GET['view'] ?? ($user ? 'my' : 'public'); // 'my' (default) or 'public'
$params = [];
$where = [];

if (!$user && $viewMode !== 'public') {
    header('Location: /login.php');
    exit;
}

if ($viewMode === 'public') {
    $where[] = 'posts.visibility = "public"';
    $feedTitle = 'Javno';
} else {
    // Default: My items
    $where[] = 'posts.owner_user_id = :current_user';
    $params[':current_user'] = $user['id'];
    $feedTitle = 'Moje';
}

if ($typeFilter === 'video' || $typeFilter === 'image') {
    $where[] = 'type = :type';
    $params[':type'] = $typeFilter;
}
if ($search !== '') {
    $where[] = '(title LIKE :q OR description LIKE :q OR user_id IN (SELECT id FROM users WHERE username LIKE :q))';
    $params[':q'] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);
$sql = "SELECT posts.*, users.username,
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as like_count,
        (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id) as comment_count
        FROM posts LEFT JOIN users ON posts.user_id = users.id
        WHERE {$whereClause}
        ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

// Grouping Logic
function time_group_label(int $timestamp): string {
    $date = (new DateTimeImmutable())->setTimestamp($timestamp);
    $now = new DateTimeImmutable('now');

    // Compare dates (midnight to midnight)
    $dateYMD = $date->format('Y-m-d');
    $todayYMD = $now->format('Y-m-d');
    $yesterdayYMD = $now->modify('-1 day')->format('Y-m-d');

    if ($dateYMD === $todayYMD) return 'Danes';
    if ($dateYMD === $yesterdayYMD) return 'Včeraj';

    return $date->format('d. m. Y');
}

// Relative time helper for card meta
function time_ago(int $timestamp): string {
    $diff = time() - $timestamp;
    if ($diff < 60) return 'ravnokar';
    if ($diff < 3600) return floor($diff / 60) . ' min';
    if ($diff < 86400) return floor($diff / 3600) . ' h';
    return date('d.m.', $timestamp);
}

$grouped = [];
foreach ($posts as $post) {
    $grouped[time_group_label((int)$post['created_at'])][] = $post;
}

// Optimization: Partial rendering for infinite scroll
$isPartial = isset($_GET['partial']) && $_GET['partial'] === '1';

if (!$isPartial) {
    // Render Header
    render_header('Galerija' . ($viewMode === 'public' ? ' (Javno)' : ''), $user, $typeFilter === 'video' ? 'videos' : 'feed');

    // Inject Gallery Assets
    echo '<link rel="stylesheet" href="/assets/gallery.css">';

    // Gallery Toolbar (Multi-select)
    echo '
    <div class="gallery-toolbar" id="gallery-toolbar">
        <div style="display:flex;align-items:center;gap:1rem;">
            <span style="font-weight:bold;color:white;" id="selected-count">Izbrano: 0</span>
        </div>
        <div class="actions">
            <button class="button small" id="bulk-share-btn" style="background:var(--accent);color:white;border:none;">
                <span class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:4px;">share</span> Deli javno
            </button>
            <button class="button small secondary" id="cancel-selection" style="background:rgba(255,255,255,0.1);color:white;border:none;">Prekliči</button>
        </div>
    </div>
    ';

    // Top Controls (Select Mode Toggle) - Injected below standard header controls via layout, but we need it here in content
    echo '<div style="margin: 1rem 0; display:flex; justify-content:flex-end;">';
    echo '<button class="button small secondary" id="toggle-select-mode" style="background:rgba(255,255,255,0.05);color:var(--muted);border:1px solid rgba(255,255,255,0.1);display:flex;align-items:center;gap:6px;">
            <span class="material-icons" style="font-size:16px">checklist</span> Izberi več
        </button>';
    echo '</div>';
}

if (!$posts && $page === 1 && !$isPartial) {
    echo '<div style="padding:4rem;text-align:center;color:var(--muted);font-size:1.2rem;">Ni objav. Naložite prve fotografije ali videe!</div>';
}

// Pre-calculate fallback for robustness
$fallback = '/assets/img/placeholder.svg';
$jsFallback = json_encode($fallback);

// Optimization: Global index for fetchpriority
$globalIndex = 0;

foreach ($grouped as $label => $items) {
    echo '<section class="time-group">';
    echo '<h2 style="padding: 1rem 0; color: var(--muted); font-size: 1.1rem; border-bottom: 1px solid var(--border); margin-bottom: 1.5rem;">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<div class="grid">';
    foreach ($items as $item) {
        $id = (int)$item['id'];
        $original = '/' . $item['file_path'];

        if ($item['type'] === 'image') {
            $thumb = '/thumb.php?src=' . urlencode($original) . '&w=420&h=420&fit=cover';
        } else {
            $thumb = '/' . ($item['thumb_path'] ?: $item['file_path']);
        }

        $title = $id . ' ' . ($item['type'] === 'video' ? 'video' : 'slika');
        $username = $item['username'] ?? 'neznano';
        $timeAgo = time_ago((int)$item['created_at']);
        $isPublic = ($item['visibility'] === 'public' || $item['is_public']);

        // Metadata
        $views = $item['views'] ?? 0;
        $likes = $item['like_count'] ?? 0;
        $comments = $item['comment_count'] ?? 0;

        // Robust handler: Try original if thumb fails (images only), then placeholder
        $jsOriginal = json_encode($original);

        $onError = "this.onerror=null;this.src=$jsFallback";
        if ($item['type'] === 'image') {
            $onError = "if(this.dataset.retry){this.onerror=null;this.src=$jsFallback}else{this.dataset.retry=true;this.src=$jsOriginal}";
        }

        // Optimization: Priority for first few items
        $fetchPriority = ($globalIndex < 4 && $page === 1) ? 'high' : 'low';
        $globalIndex++;

        echo '<a href="/view.php?id=' . $id . '" class="gallery-card" data-id="' . $id . '">';

        // Image Wrapper
        echo '<div class="card-image-wrapper">';
            echo '<img src="' . htmlspecialchars($thumb) . '" alt="' . htmlspecialchars($title) . '" loading="lazy" decoding="async" fetchpriority="' . $fetchPriority . '" style="object-fit: cover;" onerror="' . htmlspecialchars($onError, ENT_QUOTES) . '">';

            // Badges
            echo '<div class="card-badges">';
            if ($item['type'] === 'video') {
                echo '<span class="card-badge"><span class="material-icons" style="font-size:12px">play_arrow</span> Video</span>';
            }
            if ($isPublic) {
                echo '<span class="card-badge public"><span class="material-icons" style="font-size:12px">public</span> Javno</span>';
            }
            echo '</div>';

            // Select Indicator
            echo '<div class="card-select-overlay">';
            echo '<div class="select-indicator"></div>';
            echo '</div>';

            // Hover Overlay
            echo '<div class="card-overlay">';
                echo '<h3 class="card-title">' . htmlspecialchars($title) . '</h3>';
                echo '<div class="card-subtitle">' . htmlspecialchars($username) . '</div>';

                echo '<div class="card-meta">';
                    echo '<span class="meta-item"><span class="material-icons">schedule</span> ' . $timeAgo . '</span>';
                    if ($likes > 0) echo '<span class="meta-item"><span class="material-icons">favorite</span> ' . $likes . '</span>';
                    if ($comments > 0) echo '<span class="meta-item"><span class="material-icons">chat_bubble</span> ' . $comments . '</span>';
                    if ($views > 0) echo '<span class="meta-item"><span class="material-icons">visibility</span> ' . $views . '</span>';
                echo '</div>';
            echo '</div>'; // end overlay

        echo '</div>'; // end wrapper

        echo '</a>';
    }
    echo '</div></section>';
}

// Sentinel for Infinite Scroll
if (count($posts) > 0) {
    echo '<div id="scroll-sentinel" data-next-page="' . ($page + 1) . '" data-has-more="' . (count($posts) === $perPage ? 'true' : 'false') . '"></div>';
    echo '<div class="loading-spinner hidden" id="feed-loader" style="text-align:center;padding:2rem;color:var(--muted);">Nalaganje...</div>';
} else {
    echo '<div class="no-more-posts" style="text-align:center;padding:2rem;color:var(--muted);">Ni več objav.</div>';
}

echo '<script src="/assets/gallery.js"></script>';

if (!$isPartial) {
    render_footer();
}
