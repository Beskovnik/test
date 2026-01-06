<?php
require __DIR__ . '/app/Bootstrap.php';

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
$where = ['visibility = "public"'];
$params = [];

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
        (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id) as like_count
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
    $diff = (new DateTimeImmutable('now'))->diff((new DateTimeImmutable())->setTimestamp($timestamp));
    if ($diff->days === 0) return 'Danes';
    if ($diff->days === 1) return 'Včeraj';
    if ($diff->days <= 7) return 'Ta teden';
    if ($diff->days <= 30) return 'Ta mesec';
    if ($diff->y >= 1) return ($diff->y) . ' let nazaj';
    return 'Prej';
}

$grouped = [];
foreach ($posts as $post) {
    $grouped[time_group_label((int)$post['created_at'])][] = $post;
}

// Render
require __DIR__ . '/includes/layout.php';

render_header('Galerija', $user);

if (!$posts && $page === 1) {
    echo '<div class="empty-state">Ni objav. Naložite prve fotografije ali videe!</div>';
}

foreach ($grouped as $label => $items) {
    echo '<section class="time-group">';
    echo '<h2>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h2>';
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
        $badge = $item['type'] === 'video' ? '<span class="badge">Video</span>' : '';
        $fallback = '/assets/img/placeholder.svg';

        // Robust handler: Try original if thumb fails (images only), then placeholder
        $jsOriginal = json_encode($original);
        $jsFallback = json_encode($fallback);

        $onError = "this.onerror=null;this.src=$jsFallback";
        if ($item['type'] === 'image') {
            // If thumb.php fails, try original
            $onError = "if(this.dataset.retry){this.onerror=null;this.src=$jsFallback}else{this.dataset.retry=true;this.src=$jsOriginal}";
        } elseif ($thumb !== $original) {
             $onError = "if(this.dataset.retry){this.onerror=null;this.src=$jsFallback}else{this.dataset.retry=true;this.src=$jsOriginal}";
        }

        echo '<a href="/view.php?id=' . $id . '" class="card" data-id="' . $id . '">';
        echo '<img src="' . htmlspecialchars($thumb) . '" alt="' . htmlspecialchars($title) . '" loading="lazy" decoding="async" width="420" height="420" style="object-fit: cover;" onerror="' . htmlspecialchars($onError, ENT_QUOTES) . '">';
        echo $badge;
        echo '<div class="card-meta">';
        echo '<h3>' . htmlspecialchars($title) . '</h3>';
        if (!empty($item['username'])) {
            echo '<span>' . htmlspecialchars($item['username']) . '</span>';
        }
        echo '</div>';
        echo '</a>';
    }
    echo '</div></section>';
}

// Sentinel for Infinite Scroll
if (count($posts) > 0) {
    echo '<div id="scroll-sentinel" data-next-page="' . ($page + 1) . '" data-has-more="' . (count($posts) === $perPage ? 'true' : 'false') . '"></div>';
    echo '<div class="loading-spinner hidden" id="feed-loader">Nalaganje...</div>';
} else {
    echo '<div class="no-more-posts">Ni več objav.</div>';
}

render_footer();
