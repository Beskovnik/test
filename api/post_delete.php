<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';

$admin = require_admin($pdo);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit;
}
verify_csrf();
$postId = (int)($_POST['post_id'] ?? 0);
if ($postId <= 0) {
    flash('error', 'Invalid post.');
    redirect('/admin/posts.php');
}

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
$stmt->execute([':id' => $postId]);
$post = $stmt->fetch();
if (!$post) {
    flash('error', 'Post not found.');
    redirect('/admin/posts.php');
}

$filePath = dirname(__DIR__) . '/' . $post['file_path'];
$thumbPath = dirname(__DIR__) . '/' . $post['thumb_path'];
if (is_file($filePath)) {
    unlink($filePath);
}
if (is_file($thumbPath)) {
    unlink($thumbPath);
}

$stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
$stmt->execute([':id' => $postId]);
$meta = json_encode(['post_id' => $postId]);
$stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (:admin, :action, :meta, :created_at)');
$stmt->execute([':admin' => $admin['id'], ':action' => 'delete_post', ':meta' => $meta, ':created_at' => time()]);

flash('success', 'Post deleted.');
redirect('/admin/posts.php');
