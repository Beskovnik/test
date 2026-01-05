<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$admin = current_user($pdo);
if (!$admin || $admin['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'add') {
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] === 'admin' ? 'admin' : 'user';
    $active = !empty($input['active']) ? 1 : 0;

    if (strlen($username) < 3 || strlen($username) > 32 || !preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid username (3-32 chars, alphanumeric, ., _, -)']);
        exit;
    }

    // Check uniqueness
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u');
    $stmt->execute([':u' => $username]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['ok' => false, 'message' => 'Uporabniško ime že obstaja']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['ok' => false, 'message' => 'Geslo mora imeti vsaj 8 znakov']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at, email) VALUES (:u, :p, :r, :a, :c, NULL)');
        $stmt->execute([
            ':u' => $username,
            ':p' => password_hash($password, PASSWORD_DEFAULT),
            ':r' => $role,
            ':a' => $active,
            ':c' => time()
        ]);
        echo json_encode(['ok' => true, 'message' => 'Uporabnik uspešno dodan']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'message' => 'DB Error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'toggle') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) exit_json('Invalid ID');
    if ($id === (int)$admin['id']) exit_json('Ne morete blokirati samega sebe');

    $stmt = $pdo->prepare('UPDATE users SET active = NOT active WHERE id = :id');
    $stmt->execute([':id' => $id]);

    // Get new status
    $stmt = $pdo->prepare('SELECT active FROM users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $newStatus = (bool)$stmt->fetchColumn();

    echo json_encode(['ok' => true, 'active' => $newStatus, 'message' => $newStatus ? 'Uporabnik aktiviran' : 'Uporabnik blokiran']);
    exit;
}

if ($action === 'reset') {
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) exit_json('Invalid ID');

    $newPass = bin2hex(random_bytes(4)); // Generate random 8 char pass
    $hash = password_hash($newPass, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('UPDATE users SET pass_hash = :h WHERE id = :id');
    $stmt->execute([':h' => $hash, ':id' => $id]);

    echo json_encode(['ok' => true, 'message' => 'Geslo ponastavljeno: ' . $newPass]);
    exit;
}

function exit_json($msg) {
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}
