<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;
use App\Audit;

$admin = Auth::requireAdmin();
$pdo = Database::connect();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

verify_csrf();
$userId = (int)($_POST['user_id'] ?? 0);
$role = $_POST['role'] === 'admin' ? 'admin' : 'user';

if ($userId <= 0) {
    http_response_code(400);
    die('Invalid user.');
}

// Prevent self-demotion if only one admin?
// For now, just update.
$stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
$stmt->execute([':role' => $role, ':id' => $userId]);

Audit::log($pdo, $admin['id'], 'update_role', json_encode(['user_id' => $userId, 'role' => $role]));

flash('success', 'Vloga posodobljena.');
redirect('/admin/users.php');
