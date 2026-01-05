<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/media.php';
require __DIR__ . '/includes/layout.php';

$user = current_user($pdo);
$typeFilter = $_GET['type'] ?? null;
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 40;
$offset = ($page - 1) * $perPage;

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

$sql = 'SELECT posts.*, users.username FROM posts LEFT JOIN users ON posts.user_id = users.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$posts = $stmt->fetchAll();

function time_group_label(int $timestamp): string
{
    $now = new DateTimeImmutable('now');
    $date = (new DateTimeImmutable())->setTimestamp($timestamp);
    $diff = $now->diff($date);

    if ($diff->days === 0) {
        return 'Danes';
    }
    if ($diff->days === 1) {
        return 'Včeraj';
    }
    if ($diff->days <= 7) {
        return 'Ta teden';
    }
    if ($diff->days <= 30) {
        return 'Ta mesec';
    }
    if ($diff->y === 1) {
        return '1 leto nazaj';
    }
    if ($diff->y > 1) {
        return $diff->y . ' let nazaj';
    }
    return 'Prej';
}

$grouped = [];
foreach ($posts as $post) {
    $grouped[time_group_label((int)$post['created_at'])][] = $post;
}

render_header('Galerija', $user);
render_flash($flash ?? null);

if (!empty($errors)) {
    echo '<div class="notice error"><strong>Setup issue:</strong><ul>';
    foreach ($errors as $error) {
        echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul></div>';
}

$galleryData = [];
foreach ($posts as $post) {
    $galleryData[] = [
        'id' => (int)$post['id'],
        'type' => $post['type'],
        'file' => '/' . $post['file_path'],
        'title' => $post['title'],
    ];
}

if (!$posts) {
    echo '<div class="empty-state">Ni objav. Naložite prve fotografije ali videe!</div>';
}

foreach ($grouped as $label => $items) {
    echo '<section class="time-group">';
    echo '<h2>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<div class="grid">';
    foreach ($items as $item) {
        $thumb = '/' . $item['thumb_path'];
        $title = htmlspecialchars($item['title'] ?: 'Brez naslova', ENT_QUOTES, 'UTF-8');
        $id = (int)$item['id'];
        $badge = $item['type'] === 'video' ? '<span class="badge">Video</span>' : '';
        echo '<article class="card" data-id="' . $id . '" data-type="' . htmlspecialchars($item['type'], ENT_QUOTES, 'UTF-8') . '" data-file="/' . htmlspecialchars($item['file_path'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<img src="' . $thumb . '" alt="' . $title . '">';
        echo $badge;
        echo '<div class="card-meta">';
        echo '<h3>' . $title . '</h3>';
        if (!empty($item['username'])) {
            echo '<span>' . htmlspecialchars($item['username'], ENT_QUOTES, 'UTF-8') . '</span>';
        }
        echo '</div>';
        echo '</article>';
    }
    echo '</div></section>';
}

echo '<div class="pagination">';
if ($page > 1) {
    echo '<a class="button ghost" href="?page=' . ($page - 1) . '">Prejšnja</a>';
}
if (count($posts) === $perPage) {
    echo '<a class="button ghost" href="?page=' . ($page + 1) . '">Naslednja</a>';
}
echo '</div>';

$galleryJson = htmlspecialchars(json_encode($galleryData, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8');
echo '<script>window.galleryItems = ' . $galleryJson . ';</script>';

render_footer();
