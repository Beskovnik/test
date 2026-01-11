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

if (!isset($input['media_ids']) || !is_array($input['media_ids'])) {
    Response::error('Invalid input: media_ids required', 'INVALID_INPUT');
}

$title = trim($input['title'] ?? '');
if (empty($title)) {
    // Generate default title based on first item or date
    $title = 'Deljeno ' . date('d.m.Y');
}

$mediaIds = array_map('intval', $input['media_ids']);
if (empty($mediaIds)) {
    Response::error('No items selected', 'NO_ITEMS');
}

// Verify ownership of ALL items
$placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
$stmt = $pdo->prepare("SELECT id FROM posts WHERE id IN ($placeholders) AND owner_user_id = ?");
$params = $mediaIds;
$params[] = $user['id'];
$stmt->execute($params);
$validIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($validIds) !== count($mediaIds)) {
    Response::error('Some items do not belong to you', 'FORBIDDEN');
}

// Rate Limit (Basic: Max 10 shares per hour per user)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM shares WHERE owner_user_id = ? AND created_at > ?");
$stmt->execute([$user['id'], time() - 3600]);
if ($stmt->fetchColumn() > 20) { // relaxed to 20
    Response::error('Share limit reached (max 20/hour). Please wait.', 'RATE_LIMIT');
}

// Create Share
$token = bin2hex(random_bytes(32)); // 64 chars
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("INSERT INTO shares (owner_user_id, token, title, created_at, is_active) VALUES (?, ?, ?, ?, 1)");
    $stmt->execute([$user['id'], $token, $title, time()]);
    $shareId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO share_items (share_id, media_id) VALUES (?, ?)");
    foreach ($validIds as $mid) {
        $stmtItem->execute([$shareId, $mid]);
    }

    $pdo->commit();

    Response::json([
        'success' => true,
        'token' => $token,
        'share_url' => '/share.php?token=' . $token // Or /s/token if rewritten
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    Response::error('Database error: ' . $e->getMessage(), 'DB_ERROR');
}
