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
    <form class="card form" method="post" id="loginForm">
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
            <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required
                   placeholder="••••••••"
                   <?php if (isset($error)) echo 'aria-invalid="true"'; ?>>
        </div>

        <button class="button" type="submit" id="loginBtn" style="width: 100%; margin-top: 1rem;">Prijavi se</button>

        <p class="auth-footer">
            Nimaš računa? <a href="/register.php">Registracija</a>
        </p>
    </form>
</div>
<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
    var btn = document.getElementById('loginBtn');
    if (btn) {
        // Prevent double submission if already disabled
        if (btn.disabled) {
            e.preventDefault();
            return;
        }
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btn.innerHTML = '<span class="material-icons" style="font-size: 1.2em; animation: spin 1s linear infinite; margin-right: 0.5rem;">refresh</span> Prijavljam...';
    }
});
</script>
<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
<?php
render_footer();
?>
