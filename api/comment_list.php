<?php
require __DIR__ . '/../includes/bootstrap.php';

$postId = (int)($_GET['post_id'] ?? 0);
if (!$postId) {
    echo json_encode(['comments' => []]);
    exit;
}

$stmt = $pdo->prepare('SELECT comments.*, users.username as author FROM comments LEFT JOIN users ON comments.user_id = users.id WHERE post_id = :post ORDER BY created_at DESC');
$stmt->execute([':post' => $postId]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
foreach ($comments as $c) {
    $data[] = [
        'id' => $c['id'],
        'author' => htmlspecialchars($c['author'] ?? 'Anon', ENT_QUOTES, 'UTF-8'),
        'body' => nl2br(htmlspecialchars($c['body'], ENT_QUOTES, 'UTF-8')),
        'created_at' => $c['created_at']
    ];
}

echo json_encode(['comments' => $data]);
