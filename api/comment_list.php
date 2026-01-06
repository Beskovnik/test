<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Database;
use App\Response;

header('Content-Type: application/json');

$postId = (int)($_GET['post_id'] ?? 0);
if (!$postId) {
    Response::error('Missing post_id');
}

$pdo = Database::connect();
// Only show visible comments
$stmt = $pdo->prepare('SELECT comments.*, users.username as author
    FROM comments LEFT JOIN users ON comments.user_id = users.id
    WHERE post_id = ? AND comments.status = "visible"
    ORDER BY created_at ASC');
$stmt->execute([$postId]);
$comments = $stmt->fetchAll();

// Map for cleaner JSON
$data = array_map(function($c) {
    return [
        'id' => (int)$c['id'],
        'author' => htmlspecialchars($c['author'] ?? $c['author_name'] ?? 'Anon'),
        'body' => htmlspecialchars($c['body']),
        'created_at' => date('d.m.Y H:i', (int)$c['created_at'])
    ];
}, $comments);

Response::json(['ok' => true, 'comments' => $data]);
