<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$admin = require_admin($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId <= 0) {
        redirect('/admin/users.php');
    }

    if ($action === 'role') {
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
        $stmt->execute([':role' => $role, ':id' => $userId]);
        $meta = json_encode(['user_id' => $userId, 'role' => $role]);
        $stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (:admin, :action, :meta, :created_at)');
        $stmt->execute([':admin' => $admin['id'], ':action' => 'update_role', ':meta' => $meta, ':created_at' => time()]);
    }

    if ($action === 'toggle') {
        $active = (int)($_POST['active'] ?? 1);
        $stmt = $pdo->prepare('UPDATE users SET active = :active WHERE id = :id');
        $stmt->execute([':active' => $active, ':id' => $userId]);
        $meta = json_encode(['user_id' => $userId, 'active' => $active]);
        $stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (:admin, :action, :meta, :created_at)');
        $stmt->execute([':admin' => $admin['id'], ':action' => 'toggle_user', ':meta' => $meta, ':created_at' => time()]);
    }

    if ($action === 'reset') {
        $password = $_POST['password'] ?? '';
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET pass_hash = :hash WHERE id = :id');
            $stmt->execute([':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);
            $meta = json_encode(['user_id' => $userId]);
            $stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (:admin, :action, :meta, :created_at)');
            $stmt->execute([':admin' => $admin['id'], ':action' => 'reset_password', ':meta' => $meta, ':created_at' => time()]);
        }
    }

    flash('success', 'Posodobljeno.');
    redirect('/admin/users.php');
}

$users = $pdo->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();

render_header('Upravljanje uporabnikov', $admin, 'admin');
render_flash($flash ?? null);
?>
<div class="admin-page">
    <h1>Uporabniki</h1>
    <table>
        <thead><tr><th>Uporabnik</th><th>Email</th><th>Role</th><th>Status</th><th>Akcije</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user) : ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                        <select name="role" onchange="this.form.submit()">
                            <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>user</option>
                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                        </select>
                    </form>
                </td>
                <td><?php echo $user['active'] ? 'Active' : 'Disabled'; ?></td>
                <td>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                        <input type="hidden" name="active" value="<?php echo $user['active'] ? 0 : 1; ?>">
                        <button class="button ghost" type="submit"><?php echo $user['active'] ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
                    <form method="post" class="inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reset">
                        <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                        <input type="password" name="password" placeholder="Novo geslo" required>
                        <button class="button" type="submit">Reset</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php
render_footer();
