<?php
require __DIR__ . '/../app/Bootstrap.php';
require __DIR__ . '/../includes/layout.php';

use App\Settings;

$adminExists = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$locked = Settings::get($pdo, 'boot_locked', '0');

if ($adminExists > 0 || $locked === '1') {
    echo '<div style="color:white;text-align:center;padding:5rem;">Boot already completed. <a href="/login.php" style="color:var(--accent);">Login</a></div>';
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

    // Create Admin
    $stmt = $pdo->prepare('INSERT INTO users (username, email, pass_hash, role, created_at, active) VALUES (:username, :email, :pass_hash, :role, :created_at, 1)');
    $stmt->execute([
        ':username' => $username,
        ':email' => null,
        ':pass_hash' => password_hash($password, PASSWORD_DEFAULT),
        ':role' => 'admin',
        ':created_at' => time(),
    ]);

    Settings::set($pdo, 'boot_locked', '1');
    $_SESSION['user_id'] = (int)$pdo->lastInsertId();

    flash('success', 'Admin ustvarjen. Dobrodošli!');
    redirect('/admin/index.php');
}

render_header('Admin boot', null, 'admin');
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1 style="margin-bottom:1.5rem; text-align:center;">Inicialni Admin</h1>

        <div style="margin-bottom:1rem;">
            <label style="display:block; margin-bottom:0.5rem;">Uporabniško ime</label>
            <input type="text" name="username" required style="width:100%;">
        </div>

        <div style="margin-bottom:1.5rem;">
            <label style="display:block; margin-bottom:0.5rem;">Geslo</label>
            <input type="password" name="password" required style="width:100%;">
        </div>

        <button class="button" type="submit" style="width:100%;">Ustvari admina</button>
    </form>
</div>
<?php
render_footer();
