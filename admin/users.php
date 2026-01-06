<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;

$user = Auth::requireAdmin();
$pdo = Database::connect();

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

require __DIR__ . '/../includes/layout.php';
render_header('Uporabniki', $user, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page" style="padding: 2rem;">
    <div class="header-actions" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <h1>Uporabniki</h1>
        <button class="button" onclick="openAddUserModal()">+ Dodaj</button>
    </div>

    <div class="card form" style="margin-bottom: 2rem; min-width: auto;">
        <form class="search-form" method="get" style="display:flex; gap:1rem;">
            <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Išči..." style="flex:1;">
            <button class="button" type="submit">Išči</button>
        </form>
    </div>

    <div class="glass-card" style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                    <th style="padding: 1rem;">Uporabnik</th>
                    <th style="padding: 1rem;">Vloga</th>
                    <th style="padding: 1rem;">Status</th>
                    <th style="padding: 1rem;">Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 1rem;">
                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        <div class="muted" style="font-size: 0.85em;">ID: <?php echo $u['id']; ?></div>
                    </td>
                    <td style="padding: 1rem;"><span class="badge"><?php echo htmlspecialchars($u['role']); ?></span></td>
                    <td style="padding: 1rem;">
                        <span class="badge" style="background: <?php echo $u['active'] ? 'green' : 'red'; ?>">
                            <?php echo $u['active'] ? 'Aktiven' : 'Blokiran'; ?>
                        </span>
                    </td>
                    <td style="padding: 1rem;">
                        <div style="display: flex; gap: 0.5rem;">
                             <button class="button small ghost" onclick="toggleUser(<?php echo $u['id']; ?>, this)"><?php echo $u['active'] ? 'Blokiraj' : 'Aktiviraj'; ?></button>
                             <button class="button small ghost" onclick="resetPassword(<?php echo $u['id']; ?>)">Geslo</button>
                             <?php if ($u['id'] !== $user['id']): ?>
                                <button class="button small danger" onclick="deleteItem('user', <?php echo $u['id']; ?>, this)">Izbriši</button>
                             <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="addUserModal" style="display:none; position:fixed; inset:0; z-index:100; justify-content:center; align-items:center;">
    <div class="modal card form" style="min-width: 300px; max-width: 500px;">
        <h2>Nov uporabnik</h2>
        <form id="addUserForm">
            <?php echo csrf_field(); ?>
            <label style="display:block; margin-bottom:0.5rem;">Uporabniško ime</label>
            <input type="text" name="username" required style="width:100%; margin-bottom:1rem;">

            <label style="display:block; margin-bottom:0.5rem;">Geslo</label>
            <input type="password" name="password" required style="width:100%; margin-bottom:1rem;">

            <label style="display:block; margin-bottom:0.5rem;">Vloga</label>
            <select name="role" style="width:100%; margin-bottom:1rem;">
                <option value="user">Uporabnik</option>
                <option value="admin">Admin</option>
            </select>

            <label style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.5rem;">
                <input type="checkbox" name="active" checked> Aktiven
            </label>

            <div class="actions" style="display:flex; justify-content:flex-end; gap:1rem;">
                <button type="button" class="button ghost" onclick="closeAddUserModal()">Prekliči</button>
                <button type="submit" class="button">Shrani</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/admin.js"></script>
<?php
render_footer();
