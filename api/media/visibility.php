<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Database;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$pdo = Database::connect();

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    Response::error('Invalid JSON input', 'INVALID_JSON');
}

$token = $input['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    Response::error('CSRF Error', 'CSRF_ERROR', 403);
}

$mediaId = $input['media_id'] ?? null;
$visibility = $input['visibility'] ?? ''; // 'private' or 'public'

if (!$mediaId || !in_array($visibility, ['private', 'public'])) {
    Response::error('Invalid input', 'INVALID_INPUT');
}

// Check Ownership
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$mediaId, $user['id']]);
if (!$stmt->fetch()) {
    Response::error('Permission denied', 'FORBIDDEN', 403);
}

// Update
// If setting to private, also clear is_public flag to disable the public link
$sql = "UPDATE posts SET visibility = ?";
$params = [$visibility, $mediaId];

if ($visibility === 'private') {
    $sql .= ", is_public = 0";
}
// If setting to public, we generally leave is_public alone (it might be public via link or just visible in gallery).
// But for consistency, if 'public' is chosen, maybe we don't force is_public=1 unless generated via that specific flow?
// Let's stick to: disabling public link via "Private" toggle works.
// "Public" toggle via view.php just makes it visible in gallery, doesn't necessarily generate a token.

$stmt = $pdo->prepare($sql . " WHERE id = ?");
$stmt->execute($params);

Response::json(['success' => true]);
