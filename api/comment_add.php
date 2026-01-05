<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');
$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
verify_csrf();
$postId = (int)($_POST['post_id'] ?? 0);
$body = trim($_POST['body'] ?? '');
if ($postId <= 0 || $body === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

$stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, body, created_at, status) VALUES (:post, :user, :body, :created, :status)');
$stmt->execute([
    ':post' => $postId,
    ':user' => $user['id'],
    ':body' => $body,
    ':created' => time(),
    ':status' => 'visible',
]);

echo json_encode(['success' => true]);
