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
    <div class="ui-header space-between mb-2">
        <h1>Uporabniki</h1>
        <div class="admin-nav flex gap-s">
             <a class="button ghost" href="/admin/index.php">← Dashboard</a>
             <button class="button" onclick="openAddUserModal()"><span class="material-icons">add</span> Nov Uporabnik</button>
        </div>
    </div>

    <div class="card form mb-2">
        <form class="search-form flex gap-m" method="get">
            <div class="search-wrapper flex-1 relative">
                 <input type="search" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Išči po imenu ali emailu..." class="form-control pl-icon">
                 <span class="material-icons search-icon">search</span>
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
                    <tr><td colspan="4" class="muted" style="padding:2rem; text-align:center;">Ni uporabnikov.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <div class="user-cell flex items-center gap-s">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="font-bold"><?php echo htmlspecialchars($u['username']); ?></div>
                                    <div class="muted text-small">Pridružen <?php echo date('d.m.Y', (int)$u['created_at']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($u['email']): ?>
                                <div class="flex items-center gap-xs text-small">
                                    <span class="material-icons text-muted" style="font-size:1rem;">email</span>
                                    <?php echo htmlspecialchars($u['email']); ?>
                                </div>
                            <?php else: ?>
                                <span class="muted text-small">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="flex gap-s wrap">
                                <span class="badge"><?php echo htmlspecialchars($u['role']); ?></span>
                                <span class="badge <?php echo $u['active'] ? 'success' : 'danger'; ?>">
                                    <?php echo $u['active'] ? 'Aktiven' : 'Blokiran'; ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="flex gap-s">
                                 <button class="button small ghost icon-only" onclick="toggleUser(<?php echo $u['id']; ?>, this)" title="<?php echo $u['active'] ? 'Blokiraj' : 'Aktiviraj'; ?>">
                                     <span class="material-icons"><?php echo $u['active'] ? 'block' : 'check_circle'; ?></span>
                                 </button>
                                 <button class="button small ghost icon-only" onclick="resetPassword(<?php echo $u['id']; ?>)" title="Ponastavi geslo">
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
        <h2>Nov uporabnik</h2>
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

            <label class="checkbox-label flex items-center gap-s pointer mb-2">
                <input type="checkbox" name="active" checked class="w-auto"> Aktiven račun
            </label>

            <div class="actions flex justify-end gap-m mt-2">
                <button type="button" class="button ghost" onclick="closeAddUserModal()">Prekliči</button>
                <button type="submit" class="button">Ustvari</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/admin.js"></script>
<?php
render_footer();
