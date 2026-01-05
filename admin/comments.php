<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$admin = require_admin($pdo);

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
            $stmt = $pdo->prepare('UPDATE comments SET status = "deleted" WHERE id = :id');
            $stmt->execute([':id' => $commentId]);
        }
        $meta = json_encode(['comment_id' => $commentId, 'action' => $action]);
        $stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (:admin, :action, :meta, :created_at)');
        $stmt->execute([':admin' => $admin['id'], ':action' => 'comment_' . $action, ':meta' => $meta, ':created_at' => time()]);
    }

    flash('success', 'Komentar posodobljen.');
    redirect('/admin/comments.php');
}

$stmt = $pdo->prepare('SELECT comments.*, users.username FROM comments LEFT JOIN users ON comments.user_id = users.id ORDER BY created_at DESC');
$stmt->execute();
$comments = $stmt->fetchAll();

render_header('Moderacija komentarjev', $admin, 'admin');
render_flash($flash ?? null);
?>
<div class="admin-page">
    <h1>Komentarji</h1>
    <table>
        <thead><tr><th>Avtor</th><th>Komentar</th><th>Status</th><th>Akcije</th></tr></thead>
        <tbody>
        <?php foreach ($comments as $comment) : ?>
            <tr>
                <td><?php echo htmlspecialchars($comment['username'] ?? 'Anon', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($comment['body'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($comment['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>">
                        <input type="hidden" name="action" value="hide">
                        <button class="button ghost" type="submit">Hide</button>
                    </form>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="comment_id" value="<?php echo (int)$comment['id']; ?>">
                        <input type="hidden" name="action" value="delete">
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
