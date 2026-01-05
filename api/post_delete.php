<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$user = current_user($pdo);
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
if ($postId) {
    // Delete file
    $stmt = $pdo->prepare('SELECT file_path, thumb_path FROM posts WHERE id = :id');
    $stmt->execute([':id' => $postId]);
    $post = $stmt->fetch();

    if ($post) {
        if (file_exists(__DIR__ . '/../' . $post['file_path'])) unlink(__DIR__ . '/../' . $post['file_path']);
        if ($post['thumb_path'] && $post['thumb_path'] !== $post['file_path'] && file_exists(__DIR__ . '/../' . $post['thumb_path'])) unlink(__DIR__ . '/../' . $post['thumb_path']);

        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->execute([':id' => $postId]);
        echo json_encode(['success' => true]);
    }
}
