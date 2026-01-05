<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/layout.php';

$adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$locked = setting_get($pdo, 'boot_locked', '0');

if ($adminExists > 0 || $locked === '1') {
    echo 'Boot already completed.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        flash('error', 'Obvezni podatki.');
        redirect('/admin/boot.php');
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, email, pass_hash, role, created_at) VALUES (:username, :email, :pass_hash, :role, :created_at)');
    $stmt->execute([
        ':username' => $username,
        ':email' => null,
        ':pass_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':created_at' => time(),
    ]);
    setting_set($pdo, 'boot_locked', '1');
    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
    flash('success', 'Admin ustvarjen.');
    redirect('/admin/index.php');
}

render_header('Admin boot', null, 'admin');
render_flash($flash ?? null);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1>Inicialni admin</h1>
        <label>Uporabniško ime</label>
        <input type="text" name="username" required>
        <label>Geslo</label>
        <input type="password" name="password" required>
        <button class="button" type="submit">Ustvari admina</button>
    </form>
</div>
<?php
render_footer();
