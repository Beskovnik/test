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

        <div class="auth-header">
            <h1 class="auth-title">Galerija</h1>
            <p class="auth-subtitle">Ustvari nov račun</p>
        </div>

        <div class="form-group">
            <label for="username">Uporabniško ime</label>
            <input type="text" id="username" name="username" class="form-control" autocomplete="username" required minlength="3" pattern="^[a-zA-Z0-9_-]+$" aria-describedby="username-help" placeholder="Izberi uporabniško ime">
            <small id="username-help" style="color: var(--muted); font-size: 0.8rem; margin-top: 0.25rem; display: block;">Le črke, številke, pomišljaji in podčrtaji</small>
        </div>

        <div class="form-group">
            <label for="email">Email (opcijsko)</label>
            <input type="email" id="email" name="email" class="form-control" autocomplete="email" placeholder="tvoj@email.com">
        </div>

        <div class="form-group">
            <label for="password">Geslo</label>
            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" required minlength="8" aria-describedby="password-help" placeholder="Vsaj 8 znakov">
        </div>

        <button class="button" type="submit" style="width: 100%; margin-top: 1rem;">Ustvari račun</button>

        <p class="auth-footer">
            Že imaš račun? <a href="/login.php">Prijavi se</a>
        </p>
    </form>
</div>
<?php
render_footer();
