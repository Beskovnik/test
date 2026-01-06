<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Settings;

$user = Auth::requireAdmin();
$pdo = \App\Database::connect();

// Fetch Users
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

// Handle POST actions via simplified logic here or separate API
// For simplicity in this "reprogram", we keep POST logic for Add User here
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $u = trim($_POST['username']);
        $p = $_POST['password'];
        $r = $_POST['role'];
        $a = isset($_POST['active']) ? 1 : 0;

        if (strlen($u) >= 3 && strlen($p) >= 8) {
            $h = password_hash($p, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$u, $h, $r, $a, time()]);
                // Redirect to avoid resubmission
                header('Location: /admin/users.php');
                exit;
            } catch (PDOException $e) {
                $error = "Napaka: " . $e->getMessage();
            }
        } else {
            $error = "Prekratko ime ali geslo.";
        }
    }
}

require __DIR__ . '/../includes/layout.php';
render_header('Uporabniki', $user, 'admin');
?>
<div class="admin-page">
    <div class="header-actions">
        <h1>Uporabniki</h1>
        <button class="button" onclick="document.getElementById('addUserModal').style.display='flex'">+ Dodaj</button>
    </div>

    <?php if (isset($error)) echo "<div class='flash error'>$error</div>"; ?>

    <div class="glass-card">
        <form class="search-form" method="get">
            <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Išči...">
            <button class="button" type="submit">Išči</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Uporabnik</th>
                    <th>Vloga</th>
                    <th>Status</th>
                    <th>Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        <div class="muted">ID: <?php echo $u['id']; ?></div>
                    </td>
                    <td><span class="badge"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td>
                        <span class="badge <?php echo $u['active'] ? 'success' : 'danger'; ?>">
                            <?php echo $u['active'] ? 'Aktiven' : 'Blokiran'; ?>
                        </span>
                    </td>
                    <td>
                         <!-- Actions implemented via JS/API in a full reprogram, keeping simple here -->
                         <button class="button small ghost">Uredi</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="addUserModal" style="display:none;">
    <div class="modal card form">
        <h2>Nov uporabnik</h2>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">
            <label>Uporabniško ime</label>
            <input type="text" name="username" required>
            <label>Geslo</label>
            <input type="password" name="password" required>
            <label>Vloga</label>
            <select name="role">
                <option value="user">Uporabnik</option>
                <option value="admin">Admin</option>
            </select>
            <label><input type="checkbox" name="active" checked> Aktiven</label>
            <div class="actions">
                <button type="button" class="button ghost" onclick="this.closest('.modal-overlay').style.display='none'">Prekliči</button>
                <button type="submit" class="button">Shrani</button>
            </div>
        </form>
    </div>
</div>

<?php
render_footer();
