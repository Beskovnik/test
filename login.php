<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = :id OR email = :id) AND active = 1');
    $stmt->execute([':id' => $identifier]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['pass_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        flash('success', 'Dobrodošli nazaj!');
        redirect('/index.php');
    }
    flash('error', 'Napačni podatki.');
    redirect('/login.php');
}

render_header('Prijava', current_user($pdo));
render_flash($flash ?? null);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1>Prijava</h1>
        <label>Uporabniško ime ali email</label>
        <input type="text" name="identifier" required>
        <label>Geslo</label>
        <input type="password" name="password" required>
        <button class="button" type="submit">Prijavi se</button>
    </form>
</div>
<?php
render_footer();
