<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        flash('error', 'Uporabniško ime in geslo sta obvezna.');
        redirect('/register.php');
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users');
    $stmt->execute();
    $userCount = (int)$stmt->fetchColumn();
    $role = $userCount === 0 ? 'admin' : 'user';

    $stmt = $pdo->prepare('INSERT INTO users (username, email, pass_hash, role, created_at) VALUES (:username, :email, :pass_hash, :role, :created_at)');
    try {
        $stmt->execute([
            ':username' => $username,
            ':email' => $email ?: null,
            ':pass_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role,
            ':created_at' => time(),
        ]);
    } catch (PDOException $e) {
        flash('error', 'Uporabnik ali email že obstaja.');
        redirect('/register.php');
    }

    $_SESSION['user_id'] = (int)$pdo->lastInsertId();
    flash('success', 'Registracija uspešna.');
    redirect('/index.php');
}

render_header('Registracija', current_user($pdo));
render_flash($flash ?? null);
?>
<div class="auth-page">
    <form class="card form" method="post">
        <?php echo csrf_field(); ?>
        <h1>Registracija</h1>
        <label>Uporabniško ime</label>
        <input type="text" name="username" required>
        <label>Email (opcijsko)</label>
        <input type="email" name="email">
        <label>Geslo</label>
        <input type="password" name="password" required>
        <button class="button" type="submit">Ustvari račun</button>
    </form>
</div>
<?php
render_footer();
