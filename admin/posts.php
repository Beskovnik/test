<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$admin = require_admin($pdo);
$type = $_GET['type'] ?? '';
$author = trim($_GET['author'] ?? '');

$where = [];
$params = [];
if ($type === 'image' || $type === 'video') {
    $where[] = 'posts.type = :type';
    $params[':type'] = $type;
}
if ($author !== '') {
    $where[] = 'users.username LIKE :author';
    $params[':author'] = '%' . $author . '%';
}

$sql = 'SELECT posts.*, users.username FROM posts LEFT JOIN users ON posts.user_id = users.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY posts.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

render_header('Upravljanje objav', $admin, 'admin');
render_flash($flash ?? null);
?>
<div class="admin-page">
    <h1>Objave</h1>
    <form class="filters card" method="get" style="display: flex; gap: 10px; padding: 1rem; margin-bottom: 20px;">
        <select name="type" style="padding: 10px;">
            <option value="">Vsi tipi</option>
            <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>Slike</option>
            <option value="video" <?php echo $type === 'video' ? 'selected' : ''; ?>>Video</option>
        </select>
        <input type="text" name="author" placeholder="Avtor" value="<?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?>" style="padding: 10px;">
        <button class="button" type="submit">Filter</button>
        <a href="/admin/posts.php" class="button ghost">Počisti</a>
    </form>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--card); text-align: left;">
                    <th style="padding: 10px;">Media</th>
                    <th style="padding: 10px;">Podatki</th>
                    <th style="padding: 10px;">Statistika</th>
                    <th style="padding: 10px;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($posts as $post) : ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 10px; width: 100px;">
                        <img class="thumb" src="/<?php echo htmlspecialchars($post['thumb_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="thumb" style="width: 80px; height: 60px; object-fit: cover; border-radius: 4px;">
                    </td>
                    <td style="padding: 10px;">
                        <div><strong><?php echo htmlspecialchars($post['title'] ?: 'Brez naslova', ENT_QUOTES, 'UTF-8'); ?></strong></div>
                        <div style="color: var(--muted); font-size: 0.9em;">
                            Avtor: <?php echo htmlspecialchars($post['username'] ?? 'Anon', ENT_QUOTES, 'UTF-8'); ?><br>
                            Datum: <?php echo date('d.m.Y H:i', (int)$post['created_at']); ?><br>
                            Velikost: <?php echo round($post['size_bytes'] / 1024 / 1024, 2); ?> MB
                        </div>
                    </td>
                    <td style="padding: 10px;">
                        Views: <?php echo (int)$post['views']; ?>
                    </td>
                    <td style="padding: 10px;">
                        <div style="display: flex; gap: 5px;">
                            <a href="/<?php echo htmlspecialchars($post['file_path']); ?>" target="_blank" class="button small ghost">View</a>
                            <form method="post" action="/api/post_delete.php" class="inline" onsubmit="return confirm('Res izbrišem objavo?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                                <button class="button danger small" type="submit">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_footer();
