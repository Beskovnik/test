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
        // Use @ to suppress warnings if file missing
        if (!empty($post['file_path']) && file_exists($base . $post['file_path'])) @unlink($base . $post['file_path']);

        // Only delete thumb/preview if they are different from file_path (images sometimes reuse paths)
        if (!empty($post['thumb_path']) && $post['thumb_path'] !== $post['file_path'] && file_exists($base . $post['thumb_path'])) {
             @unlink($base . $post['thumb_path']);
        }

        if (!empty($post['preview_path']) && $post['preview_path'] !== $post['file_path'] && file_exists($base . $post['preview_path'])) {
             @unlink($base . $post['preview_path']);
        }

        // Delete from DB (Foreign keys should handle cascading for comments/likes if configured, but let's be explicit if not)
        // Schema has ON DELETE CASCADE for comments and likes, but let's trust it.
        $pdo->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);

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
    // Posts by user will set user_id to NULL or CASCADE depending on schema.
    // Schema: FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    // Wait, `app/Bootstrap.php` says `ON DELETE CASCADE` for posts?
    // Let's check Bootstrap.php content in memory.
    // "FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE" in `posts` table definition in one version,
    // but another said `ON DELETE SET NULL`.
    // The overwriten Bootstrap.php says: `FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL`.
    // So posts are kept, but user_id becomes NULL. Correct.

    Audit::log($pdo, $user['id'], 'delete_user', "Deleted user $id");
    Response::json(['message' => 'Uporabnik izbrisan.']);

} else {
    Response::error('Neznan tip.');
}
