<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$user = current_user($pdo);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if (!$postId) {
    http_response_code(400);
    exit;
}

// Check if already liked
$stmt = $pdo->prepare('SELECT 1 FROM likes WHERE post_id = :post AND user_id = :user');
$stmt->execute([':post' => $postId, ':user' => $user['id']]);
$exists = $stmt->fetchColumn();

if ($exists) {
    $stmt = $pdo->prepare('DELETE FROM likes WHERE post_id = :post AND user_id = :user');
    $liked = false;
} else {
    $stmt = $pdo->prepare('INSERT INTO likes (post_id, user_id, created_at) VALUES (:post, :user, :time)');
    $liked = true;
}
$stmt->execute([':post' => $postId, ':user' => $user['id'], ':time' => ($exists ? null : time())]);

$stmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = :post');
$stmt->execute([':post' => $postId]);
$count = (int)$stmt->fetchColumn();

echo json_encode(['liked' => $liked, 'like_count' => $count]);
