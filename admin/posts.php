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
    <form class="filters" method="get">
        <select name="type">
            <option value="">Vsi tipi</option>
            <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>Slike</option>
            <option value="video" <?php echo $type === 'video' ? 'selected' : ''; ?>>Video</option>
        </select>
        <input type="text" name="author" placeholder="Avtor" value="<?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?>">
        <button class="button" type="submit">Filter</button>
    </form>
    <table>
        <thead><tr><th>Thumb</th><th>Naslov</th><th>Tip</th><th>Avtor</th><th>Akcije</th></tr></thead>
        <tbody>
        <?php foreach ($posts as $post) : ?>
            <tr>
                <td><img class="thumb" src="/<?php echo htmlspecialchars($post['thumb_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="thumb"></td>
                <td><?php echo htmlspecialchars($post['title'] ?: 'Brez naslova', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($post['type'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($post['username'] ?? 'Anon', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form method="post" action="/api/post_delete.php" class="inline" onsubmit="return confirm('Delete post?');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="post_id" value="<?php echo (int)$post['id']; ?>">
                        <button class="button danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
