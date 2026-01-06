<?php
require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

use App\Auth;
use App\Database;
use App\Audit;

$admin = Auth::requireAdmin();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($commentId > 0) {
        if ($action === 'hide') {
            $stmt = $pdo->prepare('UPDATE comments SET status = "hidden" WHERE id = :id');
            $stmt->execute([':id' => $commentId]);
        }
        if ($action === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM comments WHERE id = :id'); // Actually delete for now, or soft delete? Old code used status='deleted'
            // Let's stick to status='deleted' to preserve history, or just delete.
            // Old code: UPDATE comments SET status = "deleted"
            // Let's use Delete for cleanup, or soft delete.
            // "DELETE FROM comments" removes it. If we want audit, we should log it.
            // If the requirement is cleanup, let's just delete it.
            // But to be safe, I will stick to soft delete if column supports it.
            // The schema says status TEXT DEFAULT "visible".
            $stmt = $pdo->prepare('UPDATE comments SET status = "deleted" WHERE id = :id');
            $stmt->execute([':id' => $commentId]);
        }
        if ($action === 'restore') {
            $stmt = $pdo->prepare('UPDATE comments SET status = "visible" WHERE id = :id');
            $stmt->execute([':id' => $commentId]);
        }

        Audit::log($pdo, $admin['id'], 'comment_' . $action, json_encode(['id' => $commentId]));
    }

    flash('success', 'Komentar posodobljen.');
    redirect('/admin/comments.php');
}

$stmt = $pdo->prepare('SELECT comments.*, users.username, posts.id as post_id, posts.type as post_type FROM comments
    LEFT JOIN users ON comments.user_id = users.id
    LEFT JOIN posts ON comments.post_id = posts.id
    ORDER BY created_at DESC LIMIT 100');
$stmt->execute();
$comments = $stmt->fetchAll();

render_header('Moderacija komentarjev', $admin, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page" style="padding: 2rem; max-width: 1400px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin:0;">Komentarji</h1>
        <div class="admin-nav">
             <a class="button ghost" href="/admin/index.php">← Dashboard</a>
        </div>
    </div>

    <div class="card" style="padding:0; overflow:hidden; cursor:default;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border); background:rgba(255,255,255,0.05);">
                        <th style="padding: 1rem;">Avtor</th>
                        <th style="padding: 1rem; width:40%;">Vsebina</th>
                        <th style="padding: 1rem;">Objava</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Akcije</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$comments): ?>
                     <tr><td colspan="5" style="padding:2rem; text-align:center; color:var(--muted);">Ni komentarjev.</td></tr>
                <?php else: ?>
                    <?php foreach ($comments as $comment) : ?>
                        <tr style="border-bottom: 1px solid var(--border); background: <?php echo $comment['status'] === 'deleted' ? 'rgba(255, 0, 0, 0.05)' : 'transparent'; ?>">
                            <td style="padding: 1rem; vertical-align:top;">
                                <strong><?php echo htmlspecialchars($comment['username'] ?? 'Anon', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                <span style="font-size:0.8rem; color:var(--muted);"><?php echo date('d.m.Y H:i', (int)$comment['created_at']); ?></span>
                            </td>
                            <td style="padding: 1rem; vertical-align:top;">
                                <div style="white-space: pre-wrap; word-break: break-word;"><?php echo htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </td>
                            <td style="padding: 1rem; vertical-align:top;">
                                <?php if ($comment['post_id']): ?>
                                    <a href="/view.php?id=<?php echo $comment['post_id']; ?>" target="_blank" style="color:var(--accent); text-decoration:underline;">
                                        #<?php echo $comment['post_id']; ?> (<?php echo $comment['post_type'] ?? 'unknown'; ?>)
                                    </a>
                                <?php else: ?>
                                    <span class="muted">Izbrisana objava</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1rem; vertical-align:top;">
                                <?php
                                $statusColor = match($comment['status']) {
                                    'visible' => 'green',
                                    'hidden' => 'orange',
                                    'deleted' => 'red',
                                    default => 'gray'
                                };
                                ?>
                                <span class="badge" style="background: <?php echo $statusColor === 'green' ? 'rgba(34, 197, 94, 0.2)' : ($statusColor === 'red' ? 'rgba(239, 68, 68, 0.2)' : 'rgba(249, 115, 22, 0.2)'); ?>; color: <?php echo $statusColor === 'green' ? '#4ade80' : ($statusColor === 'red' ? '#f87171' : '#fb923c'); ?>;">
                                    <?php echo htmlspecialchars($comment['status']); ?>
                                </span>
                            </td>
                            <td style="padding: 1rem; vertical-align:top;">
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <?php if ($comment['status'] !== 'hidden' && $comment['status'] !== 'deleted'): ?>
                                        <form method="post" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>">
                                            <input type="hidden" name="action" value="hide">
                                            <button class="button small ghost" type="submit" title="Skrij"><span class="material-icons">visibility_off</span></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($comment['status'] === 'hidden' || $comment['status'] === 'deleted'): ?>
                                        <form method="post" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <button class="button small ghost" type="submit" title="Obnovi"><span class="material-icons">restore</span></button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($comment['status'] !== 'deleted'): ?>
                                        <form method="post" style="display:inline;">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="button small danger icon-only" type="submit" title="Izbriši"><span class="material-icons">delete</span></button>
                                        </form>
                                    <?php endif; ?>
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
<?php
render_footer();
