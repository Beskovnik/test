<?php
require __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post']);
    exit;
}

// Increment view count
$stmt = $pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = :id');
$stmt->execute([':id' => $postId]);

// Fetch new view count
$stmt = $pdo->prepare('SELECT views FROM posts WHERE id = :id');
$stmt->execute([':id' => $postId]);
$views = (int)$stmt->fetchColumn();

echo json_encode(['views' => $views]);
