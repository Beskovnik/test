<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;

$user = Auth::requireAdmin();
$pdo = Database::connect();

$counts = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'comments' => 0, // Table might not exist yet or empty
];
// Check if comments table exists
$chk = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='comments'");
if ($chk->fetch()) {
    $counts['comments'] = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
}

$storageBytes = 0;
foreach (['/uploads/original', '/uploads/thumbs', '/uploads/preview'] as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (is_dir($path)) {
        foreach (glob($path . '/*') as $file) {
            if (is_file($file)) {
                $storageBytes += filesize($file);
            }
        }
    }
}

// Audit log might not exist, creating placeholder logic or table
$pdo->exec('CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user_id INTEGER,
    action TEXT,
    meta TEXT,
    created_at INTEGER
)');

$stmt = $pdo->prepare('SELECT audit_log.*, users.username FROM audit_log LEFT JOIN users ON audit_log.admin_user_id = users.id ORDER BY created_at DESC LIMIT 10');
$stmt->execute();
$audit = $stmt->fetchAll();

require __DIR__ . '/../includes/layout.php';
render_header('Admin Dashboard', $user, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page" style="padding: 2rem;">
    <h1>Nadzorna plošča</h1>

    <div class="admin-nav" style="margin-bottom: 24px; display: flex; gap: 12px;">
        <a class="button" href="/admin/users.php">Uporabniki</a>
        <a class="button" href="/admin/posts.php">Objave</a>
        <a class="button" href="/admin/settings.php">Nastavitve</a>
    </div>

    <div class="grid">
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3>Uporabniki</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo $counts['users']; ?></p>
        </div>
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3>Objave</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo $counts['posts']; ?></p>
        </div>
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3>Komentarji</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo $counts['comments']; ?></p>
        </div>
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3>Zaseden prostor</h3>
            <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo round($storageBytes / 1024 / 1024, 1); ?> MB</p>
        </div>
    </div>

    <section style="margin-top: 2rem;">
        <h2>Zadnje admin akcije</h2>
        <div class="glass-card" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                        <th style="padding: 1rem;">Admin</th>
                        <th style="padding: 1rem;">Akcija</th>
                        <th style="padding: 1rem;">Meta</th>
                        <th style="padding: 1rem;">Čas</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$audit): ?>
                    <tr><td colspan="4" style="padding: 1rem; text-align: center;" class="muted">Ni zapisov.</td></tr>
                <?php else: ?>
                    <?php foreach ($audit as $row) : ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1rem;"><?php echo htmlspecialchars($row['username'] ?? 'n/a', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 1rem;"><?php echo htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 1rem;"><?php echo htmlspecialchars($row['meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="padding: 1rem;"><?php echo date('d.m.Y H:i', (int)$row['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php
render_footer();
