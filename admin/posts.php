<?php
require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

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
$sql .= ' ORDER BY posts.created_at DESC LIMIT 100'; // Limit for performance

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll();

render_header('Upravljanje objav', $admin, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page" style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
        <h1 style="font-size: 2rem; margin:0;">Objave</h1>
        <div class="admin-nav">
             <a class="button ghost" href="/admin/index.php">← Dashboard</a>
        </div>
    </div>

    <div class="card form" style="margin-bottom: 2rem; min-width: auto; padding: 1.5rem;">
        <form class="filters" method="get" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: end;">
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <label style="font-size:0.85rem; color:var(--muted);">Tip</label>
                <select name="type" style="padding:0.6rem; border-radius:0.5rem; background:var(--input-bg); color:var(--text); border:1px solid var(--border);">
                    <option value="">Vsi tipi</option>
                    <option value="image" <?php echo $type === 'image' ? 'selected' : ''; ?>>Slike</option>
                    <option value="video" <?php echo $type === 'video' ? 'selected' : ''; ?>>Video</option>
                </select>
            </div>
            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                <label style="font-size:0.85rem; color:var(--muted);">Avtor</label>
                <input type="text" name="author" placeholder="Uporabniško ime..." value="<?php echo htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?>" style="padding:0.6rem; border-radius:0.5rem; background:var(--input-bg); color:var(--text); border:1px solid var(--border);">
            </div>
            <div style="display:flex; gap:0.5rem;">
                <button class="button" type="submit">Filtriraj</button>
                <a href="/admin/posts.php" class="button ghost">Počisti</a>
            </div>
        </form>
    </div>

    <div class="card" style="padding:0; overflow:hidden; cursor:default;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); background:rgba(255,255,255,0.05);">
                        <th style="padding: 1rem;">Predogled</th>
                        <th style="padding: 1rem;">Podatki</th>
                        <th style="padding: 1rem;">Statistika</th>
                        <th style="padding: 1rem;">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$posts): ?>
                    <tr><td colspan="4" style="padding:2rem; text-align:center; color:var(--muted);">Ni najdenih objav.</td></tr>
                <?php else: ?>
                    <?php foreach ($posts as $post) : ?>
                        <tr style="border-bottom: 1px solid var(--border);" data-id="<?php echo $post['id']; ?>">
                            <td style="padding: 1rem; width: 120px;">
                                <?php
                                $thumb = $post['thumb_path'] ?: $post['file_path'];
                                // If thumb starts with /, remove it for path join check? No, browser needs it.
                                $displayThumb = '/' . ltrim($thumb, '/');
                                ?>
                                <img class="thumb" src="<?php echo htmlspecialchars($displayThumb, ENT_QUOTES, 'UTF-8'); ?>" alt="thumb" style="width: 100px; height: 75px; object-fit: cover; border-radius: 0.5rem; background:rgba(0,0,0,0.3);" loading="lazy">
                            </td>
                            <td style="padding: 1rem;">
                                <div style="font-weight: bold; margin-bottom: 0.25rem; font-size: 1rem; color:var(--text);">
                                    <?php echo $post['id']; ?> <span class="badge" style="position:static; font-size:0.75em; vertical-align:middle; margin-left:0.5rem;"><?php echo $post['type']; ?></span>
                                </div>
                                <div style="color: var(--muted); font-size: 0.85rem; line-height: 1.5;">
                                    Avtor: <strong style="color:var(--text);"><?php echo htmlspecialchars($post['username'] ?? 'Izbrisan', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                    <span title="<?php echo date('d.m.Y H:i:s', (int)$post['created_at']); ?>">
                                        <?php echo date('d.m.Y', (int)$post['created_at']); ?>
                                    </span> •
                                    <?php echo round($post['size_bytes'] / 1024 / 1024, 2); ?> MB
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display:flex; flex-direction:column; gap:0.3rem; font-size:0.9rem;">
                                    <span><span class="material-icons" style="font-size:0.9rem; vertical-align:middle;">visibility</span> <?php echo (int)$post['views']; ?></span>
                                    <span><span class="material-icons" style="font-size:0.9rem; vertical-align:middle;">open_in_new</span> <?php echo htmlspecialchars($post['visibility']); ?></span>
                                </div>
                            </td>
                            <td style="padding: 1rem;">
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <a href="/view.php?id=<?php echo $post['id']; ?>" target="_blank" class="button small ghost" title="Ogled"><span class="material-icons">visibility</span></a>
                                    <button class="button danger small icon-only" onclick="deleteItem('post', <?php echo $post['id']; ?>, this)" title="Izbriši"><span class="material-icons">delete</span></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="/assets/js/admin.js"></script>
<?php
render_footer();
