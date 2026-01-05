<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$user = require_admin($pdo);

$counts = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'comments' => (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
];

$storageBytes = 0;
foreach (['/uploads', '/thumbs'] as $dir) {
    $path = dirname(__DIR__) . $dir;
    foreach (glob($path . '/*') as $file) {
        if (is_file($file)) {
            $storageBytes += filesize($file);
        }
    }
}

$stmt = $pdo->prepare('SELECT audit_log.*, users.username FROM audit_log LEFT JOIN users ON audit_log.admin_user_id = users.id ORDER BY created_at DESC LIMIT 10');
$stmt->execute();
$audit = $stmt->fetchAll();

render_header('Admin Dashboard', $user, 'admin');
render_flash($flash ?? null);
?>
<div class="admin-page">
    <div class="stats">
        <div class="stat">Uporabniki <strong><?php echo $counts['users']; ?></strong></div>
        <div class="stat">Objave <strong><?php echo $counts['posts']; ?></strong></div>
        <div class="stat">Komentarji <strong><?php echo $counts['comments']; ?></strong></div>
        <div class="stat">Storage <strong><?php echo round($storageBytes / 1024 / 1024, 1); ?> MB</strong></div>
    </div>
    <section>
        <h2>Zadnje admin akcije</h2>
        <table>
            <thead><tr><th>Admin</th><th>Akcija</th><th>Meta</th><th>Čas</th></tr></thead>
            <tbody>
            <?php foreach ($audit as $row) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username'] ?? 'n/a', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo date('d.m.Y H:i', (int)$row['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<?php
render_footer();
