<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;
use App\Response;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['id'])) {
    Response::error('Missing ID');
}

$token = $input['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    Response::error('CSRF Error', 'CSRF', 403);
}

$id = (int)$input['id'];
$pdo = Database::connect();

// Verify Ownership or Admin
$stmt = $pdo->prepare('SELECT user_id, file_path, thumb_path, preview_path FROM posts WHERE id = ?');
$stmt->execute([$id]);
$post = $stmt->fetch();

if (!$post) {
    Response::error('Not found', 'NOT_FOUND', 404);
}

if ($user['role'] !== 'admin' && $user['id'] !== $post['user_id']) {
    Response::error('Forbidden', 'FORBIDDEN', 403);
}

// Delete Files
$files = [
    $post['file_path'],
    $post['thumb_path'],
    $post['preview_path']
];
foreach ($files as $f) {
    if ($f && file_exists(__DIR__ . '/../' . $f)) {
        @unlink(__DIR__ . '/../' . $f);
    }
}

// Delete DB
$pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);

Response::json(['ok' => true]);
