<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$pdo = \App\Database::connect();

// CSRF check
verify_csrf();

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? ''; // 'disable', 'delete'
$shareId = $input['share_id'] ?? null;

if (!$shareId || !is_numeric($shareId)) {
    Response::error('Invalid Share ID', 'INVALID_ID');
}

// Check Ownership
$stmt = $pdo->prepare("SELECT id FROM shares WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$shareId, $user['id']]);
if (!$stmt->fetch()) {
    Response::error('Share not found or permission denied', 'FORBIDDEN', 403);
}

if ($action === 'disable') {
    $pdo->prepare("UPDATE shares SET is_active = 0 WHERE id = ?")->execute([$shareId]);
    Response::json(['success' => true, 'message' => 'Link disabled']);
} elseif ($action === 'delete') {
    // Cascade delete handles items/events usually, but let's be safe
    // SQLite FKs are on, so DELETE FROM shares should clean up share_items
    $pdo->prepare("DELETE FROM shares WHERE id = ?")->execute([$shareId]);
    Response::json(['success' => true, 'message' => 'Link deleted']);
} else {
    Response::error('Invalid action', 'INVALID_ACTION');
}
