<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Auth;
use App\Database;
use App\Response;

header('Content-Type: application/json');

// We don't enforce login strictly, but we need user info for access check
$user = Auth::user();

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['post_id'])) {
    Response::error('Missing post_id');
}

// CSRF Check
// We verify CSRF to prevent external automated spamming of view counts
verify_csrf();

$postId = (int)$input['post_id'];
$pdo = Database::connect();

try {
    // Check post existence and visibility
    $stmt = $pdo->prepare('SELECT visibility FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post) {
        Response::error('Post not found', 'NOT_FOUND', 404);
    }

    // Access Check logic matching view.php
    // Note: If view.php redirects anonymous users for non-public posts, we deny them here too.
    if ($post['visibility'] !== 'public' && !$user) {
        Response::error('Unauthorized', 'UNAUTHORIZED', 403);
    }

    // Increment
    $pdo->prepare('UPDATE posts SET views = views + 1 WHERE id = ?')->execute([$postId]);

    // Get new count
    $stmt = $pdo->prepare('SELECT views FROM posts WHERE id = ?');
    $stmt->execute([$postId]);
    $views = (int)$stmt->fetchColumn();

    Response::json(['ok' => true, 'views' => $views]);

} catch (Exception $e) {
    Response::error($e->getMessage());
}
