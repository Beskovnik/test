<?php
require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$user = current_user($pdo);
if (!$user) {
    http_response_code(401);
    exit;
}

// Simple CSRF check for API
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF mismatch']);
    exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$body = trim($_POST['body'] ?? '');

if ($postId && $body) {
    $stmt = $pdo->prepare('INSERT INTO comments (post_id, user_id, body, created_at) VALUES (:post, :user, :body, :time)');
    $stmt->execute([
        ':post' => $postId,
        ':user' => $user['id'],
        ':body' => $body,
        ':time' => time()
    ]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing data']);
}
