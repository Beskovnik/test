<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Database;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$pdo = Database::connect();

$input = json_decode(file_get_contents('php://input'), true);

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
$stmt = $pdo->prepare("UPDATE posts SET visibility = ? WHERE id = ?");
$stmt->execute([$visibility, $mediaId]);

Response::json(['success' => true]);
