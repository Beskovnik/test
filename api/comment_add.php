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
    Response::error('Komentar je predolg (max 1000 znakov).');
}
if (strlen($body) < 1) {
    Response::error('Prazen komentar.');
}

$pdo = Database::connect();
// Check if post exists
$stmt = $pdo->prepare('SELECT id FROM posts WHERE id = ?');
$stmt->execute([$postId]);
if (!$stmt->fetch()) {
    Response::error('Objava ne obstaja.');
}

$stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, author_name, body, created_at, status) VALUES (?, ?, ?, ?, ?, "visible")');
$stmt->execute([$postId, $user['id'], $user['username'], $body, time()]);

Response::json(['ok' => true]);
