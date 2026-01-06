<?php
require __DIR__ . '/../app/Bootstrap.php';

use App\Database;

// 1. Validate Request
$token = $_GET['token'] ?? '';
$mediaId = isset($_GET['media_id']) ? (int)$_GET['media_id'] : 0;

if (empty($token) || strlen($token) !== 64 || !$mediaId) {
    http_response_code(400);
    die('Invalid request');
}

$pdo = Database::connect();

// 2. Validate Access via Share Token
$stmt = $pdo->prepare("
    SELECT p.*, s.id as share_id
    FROM share_items si
    JOIN shares s ON si.share_id = s.id
    JOIN posts p ON si.media_id = p.id
    WHERE s.token = ? AND s.is_active = 1 AND p.id = ?
");
$stmt->execute([$token, $mediaId]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(403);
    die('File not found or access denied');
}

// 3. Track Download
// Hash IP + Salt
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$dateSalt = date('Y-m-d');
$ipHash = hash('sha256', $ip . $dateSalt . 'SHARE_SALT');

// Insert Event
try {
    $now = time();
    $pdo->prepare("INSERT INTO share_events (share_id, media_id, event_type, occurred_at, ip_hash) VALUES (?, ?, 'download', ?, ?)")
        ->execute([$item['share_id'], $mediaId, $now, $ipHash]);

    // Update Aggregates
    $pdo->prepare("UPDATE shares SET downloads_total = downloads_total + 1, last_download_at = ? WHERE id = ?")
        ->execute([$now, $item['share_id']]);
} catch (Exception $e) {
    // ignore
}

// 4. Serve File
$filepath = __DIR__ . '/../' . $item['file_path'];
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File missing on disk');
}

$filename = ($item['title'] ?: 'file') . '.' . pathinfo($filepath, PATHINFO_EXTENSION);
// Sanitize filename for header
$safeFilename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

// Headers
header('Content-Description: File Transfer');
header('Content-Type: ' . ($item['mime'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
