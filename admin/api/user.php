<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Database;
use App\Audit;

// Ensure JSON
header('Content-Type: application/json');

// Check Auth
$user = Auth::requireAdmin();

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Parse Input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fallback if form data is used instead of JSON body
    $input = $_POST;
}

// CSRF Check
$token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    Response::error('Invalid CSRF Token', 'CSRF_ERROR', 403);
}

$action = $input['action'] ?? '';
$pdo = Database::connect();

if ($action === 'add') {
    $u = trim($input['username'] ?? '');
    $p = $input['password'] ?? '';
    $email = trim($input['email'] ?? '');
    $r = $input['role'] ?? 'user';
    $a = isset($input['active']) && $input['active'] ? 1 : 0;

    if (strlen($u) < 3 || strlen($p) < 8) {
        Response::error('Uporabniško ime (min 3) ali geslo (min 8) je prekratko.');
    }

    // Validate username regex
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $u)) {
        Response::error('Uporabniško ime lahko vsebuje le črke, številke, pomišljaje in podčrtaje.');
    }

    // Check exist
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)');
    $stmt->execute([$u, $email ?: '']);
    if ($stmt->fetch()) {
        Response::error('Uporabnik ali email že obstaja.');
    }

    $h = password_hash($p, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (username, email, pass_hash, role, active, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$u, $email ?: null, $h, $r, $a, time()]);

    Audit::log($pdo, $user['id'], 'user_add', "Added user $u");
    Response::json(['message' => 'Uporabnik dodan.']);

} elseif ($action === 'toggle') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) Response::error('Manjka ID');
    if ($id === $user['id']) Response::error('Ne morete blokirati samega sebe.');

    // Get current
    $stmt = $pdo->prepare('SELECT active FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $curr = $stmt->fetchColumn();

    $newState = $curr ? 0 : 1;
    $stmt = $pdo->prepare('UPDATE users SET active = ? WHERE id = ?');
    $stmt->execute([$newState, $id]);

    Audit::log($pdo, $user['id'], 'user_toggle', "Toggled user $id to $newState");
    Response::json(['message' => 'Status spremenjen.', 'active' => (bool)$newState]);

} elseif ($action === 'reset') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) Response::error('Manjka ID');

    $newPass = bin2hex(random_bytes(4)); // 8 chars
    $h = password_hash($newPass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE users SET pass_hash = ? WHERE id = ?');
    $stmt->execute([$h, $id]);

    Audit::log($pdo, $user['id'], 'user_reset', "Reset password for user $id");
    Response::json(['message' => "Geslo ponastavljeno. Novo geslo: $newPass"]);

} else {
    Response::error('Neznana akcija');
}
