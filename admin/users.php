<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$admin = require_admin($pdo);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    // Add User
    if ($action === 'add') {
        verify_csrf();
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'user';
        $active = isset($_POST['active']) ? 1 : 0;

        if (strlen($username) < 3 || strlen($password) < 8) {
            flash('error', 'Prekratko uporabniško ime ali geslo.');
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at) VALUES (:u, :p, :r, :a, :c)');
                $stmt->execute([
                    ':u' => $username,
                    ':p' => $hash,
                    ':r' => $role,
                    ':a' => $active,
                    ':c' => time()
                ]);
                flash('success', 'Uporabnik dodan.');
            } catch (PDOException $e) {
                flash('error', 'Napaka: Uporabnik verjetno že obstaja.');
            }
        }
        redirect('/admin/users.php');
    }

    // API like actions via GET/AJAX usually, but here we might use simple forms or JS fetch
}

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

render_header('Upravljanje uporabnikov', $admin, 'admin');
render_flash($flash ?? null);
?>
<div class="admin-page" style="padding: 2rem; max-width: 1200px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Uporabniki</h1>
        <button class="button" onclick="openAddUserModal()">
            <span style="margin-right: 8px;">+</span> Dodaj uporabnika
        </button>
    </div>

    <div class="glass-card" style="padding: 1.5rem; margin-bottom: 2rem; background: var(--card); border-radius: 1.5rem; border: 1px solid var(--border);">
        <form class="search-form" method="get" style="display: flex; gap: 1rem;">
            <input type="search" name="q" placeholder="Išči po imenu..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1; padding: 0.75rem 1rem; border-radius: 0.75rem; border: 1px solid var(--border); background: var(--input-bg); color: var(--text);">
            <button class="button" type="submit">Išči</button>
        </form>
    </div>

    <div class="glass-card" style="overflow: hidden; background: var(--card); border-radius: 1.5rem; border: 1px solid var(--border);">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: rgba(255,255,255,0.05); text-align: left;">
                    <th style="padding: 1.25rem;">Uporabnik</th>
                    <th style="padding: 1.25rem;">Vloga</th>
                    <th style="padding: 1.25rem;">Status</th>
                    <th style="padding: 1.25rem;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u) : ?>
                <tr style="border-bottom: 1px solid var(--border);" data-id="<?php echo $u['id']; ?>">
                    <td style="padding: 1.25rem;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; background: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; color: white;">
                                <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?></div>
                                <div style="font-size: 0.8em; color: var(--muted);">ID: <?php echo $u['id']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="padding: 1.25rem;">
                        <span class="badge" style="position: static; background: <?php echo $u['role'] === 'admin' ? 'var(--accent)' : 'rgba(255,255,255,0.1)'; ?>;">
                            <?php echo htmlspecialchars($u['role']); ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem;">
                        <span class="badge" style="position: static; background: <?php echo $u['active'] ? '#10b981' : '#ef4444'; ?>;">
                            <?php echo $u['active'] ? 'Aktiven' : 'Blokiran'; ?>
                        </span>
                    </td>
                    <td style="padding: 1.25rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="button ghost small icon-only" onclick="resetPassword(<?php echo $u['id']; ?>)" title="Resetiraj geslo">🔑</button>
                            <button class="button ghost small icon-only" onclick="toggleUser(<?php echo $u['id']; ?>, this)" title="<?php echo $u['active'] ? 'Blokiraj' : 'Aktiviraj'; ?>">
                                <?php echo $u['active'] ? '⛔' : '✅'; ?>
                            </button>
                            <?php if ($u['id'] !== $admin['id']): ?>
                                <button class="button danger small icon-only" onclick="deleteItem('user', <?php echo $u['id']; ?>, this)" title="Izbriši">🗑️</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 100; justify-content: center; align-items: center;">
    <div class="modal card form" style="width: 100%; max-width: 400px; margin: 2rem;">
        <h2 style="margin-top: 0;">Nov uporabnik</h2>
        <form action="/admin/users.php" method="post" id="addUserForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Uporabniško ime</label>
                <input type="text" name="username" required minlength="3" style="width: 100%;">
            </div>

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Geslo</label>
                <input type="password" name="password" required minlength="8" style="width: 100%;">
            </div>

            <div class="form-group">
                <label style="display: block; margin-bottom: 0.5rem;">Vloga</label>
                <select name="role" style="width: 100%;">
                    <option value="user">Uporabnik</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="active" checked id="activeCheck" style="width: auto;">
                <label for="activeCheck">Aktiven</label>
            </div>

            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" class="button ghost" onclick="closeAddUserModal()" style="flex: 1;">Prekliči</button>
                <button type="submit" class="button" style="flex: 1;">Shrani</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddUserModal() {
    const modal = document.getElementById('addUserModal');
    modal.style.display = 'flex';
}
function closeAddUserModal() {
    const modal = document.getElementById('addUserModal');
    modal.style.display = 'none';
}
// Close on click outside
document.getElementById('addUserModal').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeAddUserModal();
});
</script>
<script src="/assets/js/admin.js"></script>
<?php
render_footer();
