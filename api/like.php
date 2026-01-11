<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;
use App\Response;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['post_id'])) {
    Response::error('Missing post_id');
}

// CSRF Check
verify_csrf();

$postId = (int)$input['post_id'];
$pdo = Database::connect();

try {
    // Check if liked
    // Note: 'likes' table schema was updated to composite primary key (user_id, post_id) in Bootstrap,
    // but legacy schema might have 'id'.
    // Let's use user_id AND post_id for deletion to be safe for both.

    $stmt = $pdo->prepare('SELECT 1 FROM likes WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, $user['id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Unlike
        $pdo->prepare('DELETE FROM likes WHERE post_id = ? AND user_id = ?')->execute([$postId, $user['id']]);
        $liked = false;
    } else {
        // Like
        $pdo->prepare('INSERT INTO likes (post_id, user_id, created_at) VALUES (?, ?, ?)')
            ->execute([$postId, $user['id'], time()]);
        $liked = true;
    }

    // Count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = ?');
    $stmt->execute([$postId]);
    $count = (int)$stmt->fetchColumn();

    Response::json(['ok' => true, 'liked' => $liked, 'count' => $count]);

} catch (Exception $e) {
    Response::error($e->getMessage());
}
