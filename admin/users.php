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

    // Add user
    if ($action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';

        if ($username && $password) {
            try {
                // Email defaults to NULL if not provided to allow multiple users without email
                $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at, email) VALUES (:u, :p, :r, 1, :c, :e)');
                $stmt->execute([
                    ':u' => $username,
                    ':p' => password_hash($password, PASSWORD_DEFAULT),
                    ':r' => $role,
                    ':c' => time(),
                    ':e' => null
                ]);
                flash('success', 'Uporabnik dodan.');
            } catch (PDOException $e) {
                // Log the actual error for debugging, although it's likely a duplicate constraint.
                error_log("Add user error: " . $e->getMessage());
                flash('error', 'Uporabnik že obstaja ali napaka.');
            }
        }
    }

    flash('success', 'Posodobljeno.');
    redirect('/admin/users.php');
}

$search = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM users';
$params = [];
if ($search) {
    $sql .= ' WHERE username LIKE :q OR email LIKE :q';
    $params[':q'] = '%' . $search . '%';
}
$sql .= ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

render_header('Upravljanje uporabnikov', $admin, 'admin');
render_flash($flash ?? null);
?>
<div class="admin-page">
    <h1>Uporabniki</h1>

    <div class="actions-bar" style="margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap;">
        <form class="search-form form" method="get" style="display: flex; gap: 10px; align-items: center;">
            <input type="search" name="q" placeholder="Išči uporabnike..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="button" type="submit">Išči</button>
        </form>

        <form class="add-user-form card form" method="post" style="padding: 1rem; display: flex; gap: 10px; align-items: center;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <strong>Nov uporabnik:</strong>
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <button class="button small" type="submit">Dodaj</button>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--card); text-align: left;">
                    <th style="padding: 10px;">Uporabnik</th>
                    <th style="padding: 10px;">Role</th>
                    <th style="padding: 10px;">Status</th>
                    <th style="padding: 10px;">Geslo</th>
                    <th style="padding: 10px;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user) : ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 10px;">
                        <strong><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div style="font-size: 0.8em; color: var(--muted);">ID: <?php echo $user['id']; ?></div>
                    </td>
                    <td style="padding: 10px;">
                        <form method="post" class="inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="role">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                            <select name="role" onchange="this.form.submit()" style="padding: 5px;">
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>user</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>admin</option>
                            </select>
                        </form>
                    </td>
                    <td style="padding: 10px;">
                        <span class="badge" style="position: static; background: <?php echo $user['active'] ? 'green' : 'red'; ?>;">
                            <?php echo $user['active'] ? 'Aktiven' : 'Blokiran'; ?>
                        </span>
                    </td>
                    <td style="padding: 10px;">
                         <form method="post" class="inline" style="display: flex; gap: 5px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="reset">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                            <input type="password" name="password" placeholder="Novo geslo" required style="width: 100px; padding: 5px;">
                            <button class="button small" type="submit">Reset</button>
                        </form>
                    </td>
                    <td style="padding: 10px;">
                        <form method="post" class="inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?php echo (int)$user['id']; ?>">
                            <input type="hidden" name="active" value="<?php echo $user['active'] ? 0 : 1; ?>">
                            <button class="button ghost small" type="submit">
                                <?php echo $user['active'] ? 'Blokiraj' : 'Aktiviraj'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
render_footer();
