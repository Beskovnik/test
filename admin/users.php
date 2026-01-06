<?php
require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

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

render_header('Uporabniki', $user, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="admin-page">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin:0;">Uporabniki</h1>
        <div class="admin-nav" style="display:flex; gap:0.5rem;">
             <a class="button ghost" href="/admin/index.php">← Dashboard</a>
             <button class="button" onclick="openAddUserModal()"><span class="material-icons" style="font-size:1.2rem; margin-right:0.3rem;">add</span> Nov Uporabnik</button>
        </div>
    </div>

    <div class="card form" style="margin-bottom: 2rem; min-width: auto; padding: 1rem;">
        <form class="search-form" method="get" style="display:flex; gap:1rem;">
            <div style="position:relative; flex:1;">
                 <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Išči po imenu ali emailu..." class="form-control" style="padding-left:2.5rem;">
                 <span class="material-icons" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--muted); pointer-events:none;">search</span>
            </div>
            <button class="button" type="submit">Išči</button>
        </form>
    </div>

    <div class="admin-table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Uporabnik</th>
                    <th>Kontakt</th>
                    <th>Status</th>
                    <th>Akcije</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="4" style="padding:2rem; text-align:center; color:var(--muted);">Ni uporabnikov.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <div style="width:2.5rem; height:2.5rem; background:linear-gradient(135deg, var(--accent), #7b61ff); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; color:white;">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight:600; font-size:1rem;"><?php echo htmlspecialchars($u['username']); ?></div>
                                    <div class="muted" style="font-size: 0.8em; color:var(--muted);">Pridružen <?php echo date('d.m.Y', (int)$u['created_at']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['email']): ?>
                                <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.9rem;">
                                    <span class="material-icons" style="font-size:1rem; color:var(--muted);">email</span>
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--muted); font-size:0.9rem;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                                <span class="badge"><?php echo htmlspecialchars($u['role']); ?></span>
                                <span class="badge <?php echo $u['active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $u['active'] ? 'Aktiven' : 'Blokiran'; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; gap: 0.5rem;">
                                 <button class="button small ghost" onclick="toggleUser(<?php echo $u['id']; ?>, this)" title="<?php echo $u['active'] ? 'Blokiraj' : 'Aktiviraj'; ?>">
                                     <span class="material-icons"><?php echo $u['active'] ? 'block' : 'check_circle'; ?></span>
                                 </button>
                                 <button class="button small ghost" onclick="resetPassword(<?php echo $u['id']; ?>)" title="Ponastavi geslo">
                                     <span class="material-icons">vpn_key</span>
                                 </button>
                                 <?php if ($u['id'] !== $user['id']): ?>
                                    <button class="button small danger icon-only" onclick="deleteItem('user', <?php echo $u['id']; ?>, this)" title="Izbriši">
                                        <span class="material-icons">delete</span>
                                    </button>
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

<!-- Modal -->
<div class="modal-overlay" id="addUserModal" style="display:none;">
    <div class="modal">
        <h2 style="margin-top:0;">Nov uporabnik</h2>
        <form id="addUserForm">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label>Uporabniško ime</label>
                <input type="text" name="username" required class="form-control">
            </div>

            <div class="form-group">
                <label>Email (opcijsko)</label>
                <input type="email" name="email" class="form-control">
            </div>

            <div class="form-group">
                <label>Geslo</label>
                <input type="password" name="password" required class="form-control">
            </div>

            <div class="form-group">
                <label>Vloga</label>
                <select name="role" class="form-control">
                    <option value="user">Uporabnik</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <label style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1.5rem; cursor:pointer;">
                <input type="checkbox" name="active" checked style="width:auto;"> Aktiven račun
            </label>

            <div class="actions" style="display:flex; justify-content:flex-end; gap:1rem; margin-top:2rem;">
                <button type="button" class="button ghost" onclick="closeAddUserModal()">Prekliči</button>
                <button type="submit" class="button">Ustvari</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/admin.js"></script>
<?php
render_footer();
