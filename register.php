<?php
require __DIR__ . '/app/Bootstrap.php';
require __DIR__ . '/includes/layout.php'; // For render_header

use App\Database;
use App\Auth;

if (Auth::user()) {
    redirect('/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($username) < 3 || strlen($password) < 8) {
        flash('error', 'Uporabniško ime (min 3) in geslo (min 8) sta obvezna.');
        redirect('/register.php');
    }

    // Security: Validate username characters (Alphanumeric, underscore, dash)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
        flash('error', 'Uporabniško ime lahko vsebuje le črke, številke, pomišljaje in podčrtaje.');
        redirect('/register.php');
    }

    $pdo = Database::connect();

    // Check existing
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)');
    $stmt->execute([$username, $email ?: '']);
    if ($stmt->fetch()) {
        flash('error', 'Uporabnik ali email že obstaja.');
        redirect('/register.php');
    }

    // First user is admin
    $stmt = $pdo->query('SELECT COUNT(*) FROM users');
    $userCount = (int)$stmt->fetchColumn();
    $role = $userCount === 0 ? 'admin' : 'user';

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, email, pass_hash, role, created_at, active) VALUES (:username, :email, :pass_hash, :role, :created_at, 1)');
        $stmt->execute([
            ':username' => $username,
            ':email' => $email ?: null,
            ':pass_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role,
            ':created_at' => time(),
        ]);

        $_SESSION['user_id'] = (int)$pdo->lastInsertId();
        flash('success', 'Registracija uspešna. Dobrodošli!');
        redirect('/index.php');
    } catch (PDOException $e) {
        flash('error', 'Napaka baze.');
        redirect('/register.php');
    }
}

render_header('Registracija', null);
render_flash($_SESSION['flash'] ?? null);
unset($_SESSION['flash']);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <h1 style="margin: 0; font-size: 2rem; background: linear-gradient(135deg, #fff, #a5b4fc); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Galerija</h1>
            <p style="margin: 0.5rem 0 0; color: var(--muted);">Ustvari nov račun</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem;">
            <label for="username" style="color:var(--muted); font-size:0.9rem; font-weight:500;">Uporabniško ime</label>
            <input type="text" id="username" name="username" autocomplete="username" required minlength="3" pattern="^[a-zA-Z0-9_-]+$" aria-describedby="username-help">
            <small id="username-help" style="color: var(--muted); font-size: 0.85rem;">Le črke, številke, pomišljaji in podčrtaji</small>
        </div>

        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem;">
            <label for="email" style="color:var(--muted); font-size:0.9rem; font-weight:500;">Email (opcijsko)</label>
            <input type="email" id="email" name="email" autocomplete="email">
        </div>

        <div style="display:flex; flex-direction:column; gap:0.5rem; margin-bottom:1rem;">
            <label for="password" style="color:var(--muted); font-size:0.9rem; font-weight:500;">Geslo</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8" aria-describedby="password-help">
            <small id="password-help" style="color: var(--muted); font-size: 0.85rem;">Vsaj 8 znakov</small>
        </div>

        <button class="button" type="submit" style="margin-top: 1rem; width: 100%; justify-content: center;">Ustvari račun</button>

        <p style="margin-top: 1.5rem; text-align: center; color: var(--muted); font-size: 0.9rem;">
            Že imaš račun? <a href="/login.php" style="color: var(--accent); font-weight: 600;">Prijavi se</a>
        </p>
    </form>
</div>
<?php
render_footer();
