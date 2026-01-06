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
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <h1 style="margin: 0; font-size: 2rem; background: linear-gradient(135deg, #fff, #a5b4fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Galerija</h1>
            <p style="margin: 0.5rem 0 0; color: var(--muted);">Dobrodošli nazaj</p>
        </div>

        <?php if (isset($error)) echo "<div class='error-toast' style='padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1rem;'>$error</div>"; ?>

        <label for="identifier">Uporabniško ime ali email</label>
        <input type="text" id="identifier" name="identifier" autocomplete="username" required placeholder="Vpišite svoje podatke">

        <label for="password">Geslo</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="••••••••">

        <button class="button" type="submit" style="margin-top: 1rem; width: 100%; justify-content: center;">Prijavi se</button>

        <p style="margin-top: 1.5rem; text-align: center; color: var(--muted); font-size: 0.9rem;">
            Nimaš računa? <a href="/register.php" style="color: var(--accent); font-weight: 600;">Registracija</a>
        </p>
    </form>
</div>
<?php
render_footer();
