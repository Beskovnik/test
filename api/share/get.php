<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Response;

header('Content-Type: application/json');

// Public Endpoint (No Auth::requireLogin)
$pdo = \App\Database::connect();

$token = $_GET['token'] ?? '';
if (empty($token)) {
    Response::error('Token required', 'MISSING_TOKEN');
}

// 1. Fetch Share
$stmt = $pdo->prepare("SELECT * FROM shares WHERE token = ? AND is_active = 1");
$stmt->execute([$token]);
$share = $stmt->fetch();

if (!$share) {
    Response::error('Link invalid or expired', 'NOT_FOUND', 404);
}

// 2. Fetch Items
// Join with posts to get file info. Ensure we only get items linked to this share.
// Also join users to get owner name (optional)
$stmtItems = $pdo->prepare("
    SELECT p.id, p.title, p.type, p.file_path, p.thumb_path, p.preview_path, p.width, p.height, p.created_at, p.mime,
           u.username
    FROM share_items si
    JOIN posts p ON si.media_id = p.id
    LEFT JOIN users u ON p.owner_user_id = u.id
    WHERE si.share_id = ?
    ORDER BY p.created_at DESC
");
$stmtItems->execute([$share['id']]);
$items = $stmtItems->fetchAll();

// 3. Analytics (Async-ish, logic handled in client or separate call, but we can log view here too)
// Note: Prompt says "Ko nekdo odpre /s/<token>: zabeleži event_type='view'".
// Since this API is called by `share.php` (frontend) to get data, strictly speaking `share.php` load is the view.
// But if `share.php` renders purely via JS (calling this API), then this is the place.
// However, typically `share.php` is the page.
// Let's assume `share.php` will handle the "Page View" analytics call or we do it here.
// I'll do it here to ensure data access counts as a view.

// Debounce logic: Check if we tracked a view for this session/IP recently?
// Simplified: Just record it for now.
// Actually, let's keep analytics separate to avoid slowing down read?
// The prompt says "Server-side beleženje dogodkov (OBVEZNO)... Ko nekdo odpre /s/<token>".
// Since `share.php` will be a PHP file, I will put the View tracking there.
// This API is for data fetching.

Response::json([
    'success' => true,
    'share' => [
        'title' => $share['title'],
        'created_at' => $share['created_at'],
        'owner_name' => $items[0]['username'] ?? 'Unknown'
    ],
    'items' => $items
]);
