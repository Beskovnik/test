<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;
use App\Response;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['post_id']) || empty($input['body'])) {
    Response::error('Missing data');
}

$token = $input['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    Response::error('CSRF Error', 'CSRF', 403);
}

$postId = (int)$input['post_id'];
$body = trim($input['body']);

if (strlen($body) > 1000) {
    Response::error('Comment too long');
}

$pdo = Database::connect();
$stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, author_name, body, created_at) VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$postId, $user['id'], $user['username'], $body, time()]);

Response::json(['ok' => true]);
