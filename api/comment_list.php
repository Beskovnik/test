<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$postId = (int)($_GET['post_id'] ?? 0);
if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post']);
    exit;
}

$stmt = $pdo->prepare('SELECT comments.*, users.username FROM comments LEFT JOIN users ON comments.user_id = users.id WHERE comments.post_id = :post AND comments.status = "visible" ORDER BY created_at ASC');
$stmt->execute([':post' => $postId]);
$comments = [];
foreach ($stmt->fetchAll() as $row) {
    $comments[] = [
        'id' => (int)$row['id'],
        'author' => $row['username'] ?? 'Anon',
        'body' => $row['body'],
        'created_at' => (int)$row['created_at'],
    ];
}

echo json_encode(['comments' => $comments]);
