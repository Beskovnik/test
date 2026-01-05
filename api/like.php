<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';
require __DIR__ . '/../includes/media.php';

header('Content-Type: application/json');

$user = require_login($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}
verify_csrf();
$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post']);
    exit;
}

$stmt = $pdo->prepare('SELECT id FROM likes WHERE post_id = :post AND user_id = :user');
$stmt->execute([':post' => $postId, ':user' => $user['id']]);
$existing = $stmt->fetch();

if ($existing) {
    $stmt = $pdo->prepare('DELETE FROM likes WHERE id = :id');
    $stmt->execute([':id' => $existing['id']]);
    $liked = false;
} else {
    $stmt = $pdo->prepare('INSERT INTO likes (post_id, user_id, created_at) VALUES (:post, :user, :created)');
    $stmt->execute([':post' => $postId, ':user' => $user['id'], ':created' => time()]);
    $liked = true;
}

$likeCount = fetch_like_count($pdo, $postId);

echo json_encode(['liked' => $liked, 'like_count' => $likeCount]);
