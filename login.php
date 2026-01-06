<?php
require __DIR__ . '/app/Bootstrap.php';

use App\Auth;
use App\Settings;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    verify_csrf();

    $username = $_POST['identifier'] ?? '';
    $password = $_POST['password'] ?? '';

    $pdo = \App\Database::connect();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = :u OR email = :u) AND active = 1');
    $stmt->execute([':u' => $username]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['pass_hash'])) {
        $_SESSION['user_id'] = $u['id'];
        header('Location: /index.php');
        exit;
    } else {
        $error = "Neveljavni podatki ali blokiran račun.";
    }
}

require __DIR__ . '/includes/layout.php';
render_header('Prijava', null);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1>Prijava</h1>
        <?php if (isset($error)) echo "<p class='text-danger'>$error</p>"; ?>

        <label>Uporabniško ime ali email</label>
        <input type="text" name="identifier" required>

        <label>Geslo</label>
        <input type="password" name="password" required>

        <button class="button" type="submit">Prijavi se</button>

        <p style="margin-top: 1rem; text-align: center; color: var(--muted);">
            Nimaš računa? <a href="/register.php" style="color: var(--accent);">Registracija</a>
        </p>
    </form>
</div>
<?php
render_footer();
