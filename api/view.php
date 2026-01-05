<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId) {
    $stmt = $pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = :id');
    $stmt->execute([':id' => $postId]);

    $stmt = $pdo->prepare('SELECT views FROM posts WHERE id = :id');
    $stmt->execute([':id' => $postId]);
    echo json_encode(['views' => $stmt->fetchColumn()]);
}
