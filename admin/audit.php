<?php
require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

use App\Auth;
use App\Database;

$user = Auth::requireAdmin();
$pdo = Database::connect();

$limit = 100;
$logs = \App\Audit::getLogs($pdo, $limit);

render_header('Revizijski Dnevnik', $user, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin:0;">Revizijski Dnevnik</h1>
        <div class="admin-nav">
             <a class="button ghost" href="/admin/index.php">← Dashboard</a>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden; cursor:default;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); background:rgba(255,255,255,0.05);">
                        <th style="padding: 1rem;">Admin</th>
                        <th style="padding: 1rem;">Akcija</th>
                        <th style="padding: 1rem;">Podrobnosti</th>
                        <th style="padding: 1rem;">Čas</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="4" style="padding: 2rem; text-align: center; color: var(--muted);">Ni zapisov v dnevniku.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $row) : ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1rem; font-weight: 500;">
                                <?php echo htmlspecialchars($row['username'] ?? 'Sistem', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <span class="badge"><?php echo htmlspecialchars($row['action'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td style="padding: 1rem; color: var(--muted); word-break: break-word;">
                                <?php echo htmlspecialchars($row['meta'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td style="padding: 1rem; color: var(--muted); font-size: 0.9rem; white-space: nowrap;">
                                <?php echo date('d.m.Y H:i', (int)$row['created_at']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
render_footer();
