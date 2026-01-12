<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php'; // For render_header

use App\Auth;
use App\Database;

if (Auth::user()) {
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    verify_csrf();

    $username = trim(isset($_POST['identifier']) ? $_POST['identifier'] : '');
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $pdo = Database::connect();
    $u = null;

    // Attempt to fetch user first
    $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = :u OR email = :u) AND active = 1');
    $stmt->execute([':u' => $username]);
    $u = $stmt->fetch();

    if ($u && password_verify($password, $u['pass_hash'])) {

        // Auto-promote first user to admin logic
        $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        if ($adminCount === 0) {
            $promote = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            $promote->execute([
                ':role' => 'admin',
                ':id' => $u['id'],
            ]);
            $u['role'] = 'admin';
        }

        $_SESSION['user_id'] = $u['id'];
        redirect('/index.php');
    } else {
        $error = "Neveljavni podatki ali blokiran račun.";
    }
}

render_header('Prijava', null);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>

        <div class="auth-header">
            <h1 class="auth-title">Galerija</h1>
            <p class="auth-subtitle">Dobrodošli nazaj</p>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: rgba(255, 71, 87, 0.1); border: 1px solid var(--danger); color: #ff8fa3; padding: 0.75rem; border-radius: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                <span class="material-icons" style="font-size: 1.2rem;">error_outline</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="identifier">Uporabniško ime ali email</label>
            <input type="text" id="identifier" name="identifier" class="form-control" autocomplete="username" required
                   placeholder="Vpišite svoje podatke"
                   value="<?php echo htmlspecialchars(isset($username) ? $username : ''); ?>"
                   <?php if (isset($error)) echo 'aria-invalid="true"'; ?>>
        </div>

        <div class="form-group">
            <label for="password">Geslo</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required
                       placeholder="••••••••"
                       <?php if (isset($error)) echo 'aria-invalid="true"'; ?>>
                <button type="button" class="password-toggle" aria-label="Pokaži geslo">
                    <svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" fill="currentColor"><path d="M0 0h24v24H0V0z" fill="none"/><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                </button>
            </div>
        </div>

        <button class="button" type="submit" style="width: 100%; margin-top: 1rem;">Prijavi se</button>

        <p class="auth-footer">
            Nimaš računa? <a href="/register.php">Registracija</a>
        </p>
    </form>
</div>
<?php
render_footer();
?>
