<?php
require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

use App\Auth;
use App\Database;

$user = Auth::requireAdmin();
$pdo = Database::connect();

$counts = [
    'users' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'posts' => (int)$pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'comments' => 0,
];

try {
    $counts['comments'] = (int)$pdo->query('SELECT COUNT(*) FROM comments')->fetchColumn();
} catch (Exception $e) {}

$storageBytes = 0;
// Rough estimate
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/../uploads', RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $storageBytes += $file->getSize();
    }
}

$audit = \App\Audit::getLogs($pdo, 10);

render_header('Admin Dashboard', $user, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page">
    <h1>Nadzorna plošča</h1>

    <div class="admin-nav">
        <a class="button <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? '' : 'ghost'; ?>" href="/admin/index.php">Dashboard</a>
        <a class="button ghost" href="/admin/users.php">Uporabniki</a>
        <a class="button ghost" href="/admin/posts.php">Objave</a>
        <a class="button ghost" href="/admin/comments.php">Komentarji</a>
        <a class="button ghost" href="/admin/audit.php">Dnevnik</a>
        <a class="button ghost" href="/admin/settings.php">Nastavitve</a>
    </div>

    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 3rem;">
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3 style="color: var(--muted); font-size: 0.9rem; margin: 0 0 0.5rem 0; text-transform: uppercase; letter-spacing: 1px;">Uporabniki</h3>
            <p style="font-size: 2.5rem; font-weight: 700; margin: 0; color: var(--accent);"><?php echo $counts['users']; ?></p>
        </div>
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3 style="color: var(--muted); font-size: 0.9rem; margin: 0 0 0.5rem 0; text-transform: uppercase; letter-spacing: 1px;">Objave</h3>
            <p style="font-size: 2.5rem; font-weight: 700; margin: 0; color: #a5b4fc;"><?php echo $counts['posts']; ?></p>
        </div>
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3 style="color: var(--muted); font-size: 0.9rem; margin: 0 0 0.5rem 0; text-transform: uppercase; letter-spacing: 1px;">Komentarji</h3>
            <p style="font-size: 2.5rem; font-weight: 700; margin: 0; color: #86efac;"><?php echo $counts['comments']; ?></p>
        </div>
        <div class="card" style="padding: 1.5rem; height: auto;">
            <h3 style="color: var(--muted); font-size: 0.9rem; margin: 0 0 0.5rem 0; text-transform: uppercase; letter-spacing: 1px;">Zaseden prostor</h3>
            <p style="font-size: 2.5rem; font-weight: 700; margin: 0; color: #fca5a5;"><?php echo round($storageBytes / 1024 / 1024, 1); ?> MB</p>
        </div>
    </div>

    <section>
        <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Zadnje akcije</h2>
        <div class="admin-table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Akcija</th>
                        <th>Podrobnosti</th>
                        <th>Čas</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$audit): ?>
                    <tr><td colspan="4" style="padding: 2rem; text-align: center; color: var(--muted);">Ni zapisov v dnevniku.</td></tr>
                <?php else: ?>
                    <?php foreach ($audit as $row) : ?>
                        <tr>
                            <td style="font-weight: 500;"><?php echo htmlspecialchars($row['username'] ?? 'Sistem', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td style="color: var(--muted);"><?php echo htmlspecialchars($row['meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="color: var(--muted); font-size: 0.9rem;"><?php echo date('d.m.Y H:i', (int)$row['created_at']); ?></td>
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
