<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Database;
use App\Audit;

// Ensure JSON
header('Content-Type: application/json');

// Check Auth
$user = Auth::requireAdmin();

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
}

// Parse Input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

// CSRF Check
$token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    Response::error('Invalid CSRF Token', 'CSRF_ERROR', 403);
}

$type = $input['type'] ?? '';
$id = (int)($input['id'] ?? 0);
$pdo = Database::connect();

if (!$id || !$type) {
    Response::error('Manjkajoči podatki');
}

if ($type === 'post') {
    // Get file paths to delete
    $stmt = $pdo->prepare('SELECT file_path, thumb_path, preview_path FROM posts WHERE id = ?');
    $stmt->execute([$id]);
    $post = $stmt->fetch();

    if ($post) {
        $base = __DIR__ . '/../../';
        if ($post['file_path'] && file_exists($base . $post['file_path'])) unlink($base . $post['file_path']);
        if ($post['thumb_path'] && file_exists($base . $post['thumb_path']) && $post['thumb_path'] !== $post['file_path']) unlink($base . $post['thumb_path']);
        if ($post['preview_path'] && file_exists($base . $post['preview_path']) && $post['preview_path'] !== $post['file_path']) unlink($base . $post['preview_path']);

        // Delete from DB
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM comments WHERE post_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM likes WHERE post_id = ?')->execute([$id]);

        Audit::log($pdo, $user['id'], 'delete_post', "Deleted post $id");
        Response::json(['message' => 'Objava izbrisana.']);
    } else {
        Response::error('Objava ne obstaja.');
    }

} elseif ($type === 'user') {
    if ($id === $user['id']) {
        Response::error('Ne moreš izbrisati samega sebe.');
    }

    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    Audit::log($pdo, $user['id'], 'delete_user', "Deleted user $id");
    Response::json(['message' => 'Uporabnik izbrisan.']);

} else {
    Response::error('Neznan tip.');
}
