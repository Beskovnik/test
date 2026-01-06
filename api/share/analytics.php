<?php
require __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Database;

header('Content-Type: application/json');

$user = Auth::requireLogin();
$pdo = Database::connect();

$shareId = $_GET['share_id'] ?? null;
$type = $_GET['type'] ?? 'summary'; // summary, timeseries, top_items

if (!$shareId) {
    Response::error('Missing share_id', 'INVALID_INPUT');
}

// Check Ownership
$stmt = $pdo->prepare("SELECT id FROM shares WHERE id = ? AND owner_user_id = ?");
$stmt->execute([$shareId, $user['id']]);
if (!$stmt->fetch()) {
    Response::error('Permission denied', 'FORBIDDEN', 403);
}

if ($type === 'timeseries') {
    // Last 30 days
    $days = [];
    for ($i = 29; $i >= 0; $i--) {
        $days[date('Y-m-d', strtotime("-$i days"))] = ['views' => 0, 'downloads' => 0];
    }

    $stmt = $pdo->prepare("
        SELECT date(occurred_at, 'unixepoch', 'localtime') as day, event_type, COUNT(*) as count
        FROM share_events
        WHERE share_id = ? AND occurred_at > ?
        GROUP BY day, event_type
    ");
    $stmt->execute([$shareId, time() - 30*86400]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        if (isset($days[$row['day']])) {
            if ($row['event_type'] === 'view') $days[$row['day']]['views'] = (int)$row['count'];
            if ($row['event_type'] === 'download') $days[$row['day']]['downloads'] = (int)$row['count'];
        }
    }

    Response::json(['success' => true, 'data' => $days]);

} elseif ($type === 'top_items') {
    // Top items by interactions
    $stmt = $pdo->prepare("
        SELECT p.title, p.file_path, p.thumb_path, p.type,
               SUM(CASE WHEN se.event_type = 'download' THEN 1 ELSE 0 END) as downloads,
               SUM(CASE WHEN se.event_type = 'view' THEN 1 ELSE 0 END) as views -- views are per share usually, but we might track item opens later
        FROM share_events se
        JOIN posts p ON se.media_id = p.id
        WHERE se.share_id = ? AND se.media_id IS NOT NULL
        GROUP BY se.media_id
        ORDER BY downloads DESC
        LIMIT 10
    ");
    $stmt->execute([$shareId]);
    $items = $stmt->fetchAll();

    Response::json(['success' => true, 'data' => $items]);

} else {
    Response::error('Invalid type', 'INVALID_TYPE');
}
