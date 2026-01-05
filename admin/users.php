<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$admin = require_admin($pdo);

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
<div class="admin-page">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h1>Uporabniki</h1>
        <button class="button" onclick="openAddUserModal()">Dodaj uporabnika</button>
    </div>

    <div class="actions-bar" style="margin-bottom: 20px;">
        <form class="search-form form" method="get" style="display: flex; gap: 10px; align-items: center;">
            <input type="search" name="q" placeholder="Išči uporabnike..." value="<?php echo htmlspecialchars($search); ?>">
            <button class="button" type="submit">Išči</button>
        </form>
    </div>

    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--card); text-align: left;">
                    <th style="padding: 10px;">Uporabnik</th>
                    <th style="padding: 10px;">Role</th>
                    <th style="padding: 10px;">Status</th>
                    <th style="padding: 10px;">Akcije</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user) : ?>
                <tr style="border-bottom: 1px solid var(--border);" data-id="<?php echo $user['id']; ?>">
                    <td style="padding: 10px;">
                        <strong><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div style="font-size: 0.8em; color: var(--muted);">ID: <?php echo $user['id']; ?></div>
                    </td>
                    <td style="padding: 10px;">
                        <span class="badge"><?php echo htmlspecialchars($user['role']); ?></span>
                    </td>
                    <td style="padding: 10px;">
                        <span class="badge" style="background: <?php echo $user['active'] ? 'green' : 'red'; ?>;">
                            <?php echo $user['active'] ? 'Aktiven' : 'Blokiran'; ?>
                        </span>
                    </td>
                    <td style="padding: 10px;">
                        <div style="display: flex; gap: 8px;">
                            <button class="button ghost small" onclick="resetPassword(<?php echo $user['id']; ?>)" title="Resetiraj geslo">🔑</button>
                            <button class="button ghost small" onclick="toggleUser(<?php echo $user['id']; ?>, this)">
                                <?php echo $user['active'] ? 'Blokiraj' : 'Aktiviraj'; ?>
                            </button>
                            <?php if ($user['id'] !== $admin['id']): ?>
                                <button class="button danger small" onclick="deleteItem('user', <?php echo $user['id']; ?>, this)" title="Izbriši">🗑️</button>
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
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <h2>Nov uporabnik</h2>
        <form id="addUserForm" class="form">
            <div class="form-group">
                <label>Uporabniško ime</label>
                <input type="text" name="username" required minlength="3">
            </div>
            <div class="form-group">
                <label>Geslo</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <div class="form-group">
                <label>Vloga</label>
                <select name="role">
                    <option value="user">Uporabnik</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <div class="form-group" style="flex-direction: row; align-items: center; gap: 10px;">
                <input type="checkbox" name="active" checked style="width: auto;">
                <label>Aktiven</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="button ghost" onclick="closeAddUserModal()">Prekliči</button>
                <button type="submit" class="button">Shrani</button>
            </div>
        </form>
    </div>
</div>

<script src="/assets/js/admin.js"></script>
<?php
render_footer();
