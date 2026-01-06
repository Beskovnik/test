<?php
require __DIR__ . '/app/Bootstrap.php';

use App\Database;
use App\Auth;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($username) < 3 || strlen($password) < 8) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Uporabniško ime (min 3) in geslo (min 8) sta obvezna.'];
        header('Location: /register.php');
        exit;
    }

    $pdo = Database::connect();

    // Check existing
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)');
    $stmt->execute([$username, $email ?: '']);
    if ($stmt->fetch()) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Uporabnik ali email že obstaja.'];
        header('Location: /register.php');
        exit;
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
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Registracija uspešna. Dobrodošli!'];
        header('Location: /index.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Napaka baze.'];
    }
}

require __DIR__ . '/includes/layout.php';
render_header('Registracija', null);
render_flash($_SESSION['flash'] ?? null); unset($_SESSION['flash']);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1>Registracija</h1>

        <label for="username">Uporabniško ime</label>
        <input type="text" id="username" name="username" autocomplete="username" required minlength="3">

        <label for="email">Email (opcijsko)</label>
        <input type="email" id="email" name="email" autocomplete="email">

        <label for="password">Geslo</label>
        <input type="password" id="password" name="password" autocomplete="new-password" required minlength="8">

        <button class="button" type="submit">Ustvari račun</button>

        <p style="margin-top: 1rem; text-align: center; color: var(--muted);">
            Že imaš račun? <a href="/login.php" style="color: var(--accent);">Prijavi se</a>
        </p>
    </form>
</div>
<?php
render_footer();
