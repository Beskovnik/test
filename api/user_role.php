<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';

$admin = require_admin($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}
verify_csrf();
$userId = (int)($_POST['user_id'] ?? 0);
$role = $_POST['role'] === 'admin' ? 'admin' : 'user';
if ($userId <= 0) {
    http_response_code(400);
    echo 'Invalid user.';
    exit;
}

$stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
$stmt->execute([':role' => $role, ':id' => $userId]);
$meta = json_encode(['user_id' => $userId, 'role' => $role]);
$stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (:admin, :action, :meta, :created_at)');
$stmt->execute([':admin' => $admin['id'], ':action' => 'update_role', ':meta' => $meta, ':created_at' => time()]);

flash('success', 'Role updated.');
redirect('/admin/users.php');
