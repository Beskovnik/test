<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;

$admin = Auth::requireAdmin();
$pdo = Database::connect();

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

require __DIR__ . '/../includes/layout.php';
render_header('Upravljanje objav', $admin, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page" style="padding: 2rem;">
    <h1>Objave</h1>
    <div class="card form" style="margin-bottom: 2rem; min-width: auto;">
        <form class="filters" method="get" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <select name="type">
                <option value="">Vsi tipi</option>
                <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>Slike</option>
                <option value="video" <?php echo $type === 'video' ? 'selected' : ''; ?>>Video</option>
            </select>
            <input type="text" name="author" placeholder="Avtor" value="<?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?>">
            <button class="button" type="submit">Filter</button>
            <a href="/admin/posts.php" class="button ghost">Počisti</a>
        </form>
    </div>

    <div class="glass-card" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                    <th style="padding: 1rem;">Media</th>
                    <th style="padding: 1rem;">Podatki</th>
                    <th style="padding: 1rem;">Statistika</th>
                    <th style="padding: 1rem;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($posts as $post) : ?>
                <tr style="border-bottom: 1px solid var(--border);" data-id="<?php echo $post['id']; ?>">
                    <td style="padding: 1rem; width: 120px;">
                        <img class="thumb" src="/<?php echo htmlspecialchars($post['thumb_path'] ?: $post['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="thumb" style="width: 100px; height: 75px; object-fit: cover; border-radius: 0.5rem;" loading="lazy">
                    </td>
                    <td style="padding: 1rem;">
                        <div style="font-weight: bold; margin-bottom: 0.25rem;"><?php echo $post['id'] . ' ' . ($post['type'] === 'video' ? 'video' : 'slika'); ?></div>
                        <div style="color: var(--muted); font-size: 0.9em;">
                            Avtor: <?php echo htmlspecialchars($post['username'] ?? 'Anon', ENT_QUOTES, 'UTF-8'); ?><br>
                            Datum: <?php echo date('d.m.Y H:i', (int)$post['created_at']); ?><br>
                            Velikost: <?php echo round($post['size_bytes'] / 1024 / 1024, 2); ?> MB
                        </div>
                    </td>
                    <td style="padding: 1rem;">
                        Views: <?php echo (int)$post['views']; ?>
                    </td>
                    <td style="padding: 1rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="/view.php?id=<?php echo $post['id']; ?>" target="_blank" class="button small ghost">Ogled</a>
                            <button class="button danger small" onclick="deleteItem('post', <?php echo $post['id']; ?>, this)">Izbriši</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="/assets/js/admin.js"></script>
<?php
render_footer();
