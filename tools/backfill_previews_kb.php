<?php
/**
 * Backfill Preview Sizes Tool
 *
 * Iterates over all image posts and regenerates the 'thumb_path' file
 * to ensure it complies with the new preview_max_kb setting.
 */

require __DIR__ . '/../app/Bootstrap.php';

use App\Database;
use App\Settings;
use App\Media;

if (php_sapi_name() !== 'cli') {
    die("This script must be run from CLI.");
}

$pdo = Database::connect();
$previewMaxKb = (int)Settings::get($pdo, 'preview_max_kb', '100');
$maxBytes = $previewMaxKb * 1024;

echo "Starting Preview Regeneration...\n";
echo "Target Limit: {$previewMaxKb} KB ({$maxBytes} bytes)\n";

// Get all image posts
$stmt = $pdo->query("SELECT id, file_path, thumb_path, type FROM posts WHERE type = 'image' ORDER BY id DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = count($posts);
$count = 0;
$successCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($posts as $post) {
    $count++;
    echo "[{$count}/{$total}] Processing ID {$post['id']}... ";

    $originalPath = __DIR__ . '/../' . $post['file_path'];
    $thumbPathRel = $post['thumb_path'];

    // Safety check: Don't overwrite original if thumb_path == file_path (meaning no thumb existed before)
    if ($thumbPathRel === $post['file_path']) {
        // We should generate a proper thumb path now
        $ext = pathinfo($originalPath, PATHINFO_EXTENSION);
        $random = bin2hex(random_bytes(16));
        $thumbPathRel = 'uploads/thumbs/' . $random . '.webp'; // Force webp for new thumbs
    }

    $thumbPathAbs = __DIR__ . '/../' . $thumbPathRel;

    if (!file_exists($originalPath)) {
        echo "Original missing! Skipped.\n";
        $errorCount++;
        continue;
    }

    // Ensure directory exists
    $dir = dirname($thumbPathAbs);
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    try {
        // Regenerate
        $res = Media::generatePreviewUnderBytes($originalPath, $thumbPathAbs, $maxBytes, 1920, 85);

        if ($res) {
            // Update DB if path changed (e.g. if we generated a new thumb file for one that was missing)
            if ($thumbPathRel !== $post['thumb_path']) {
                $upd = $pdo->prepare("UPDATE posts SET thumb_path = ? WHERE id = ?");
                $upd->execute([$thumbPathRel, $post['id']]);
                echo "Generated & DB Updated. ";
            } else {
                echo "Regenerated. ";
            }
            $successCount++;
        } else {
            echo "Failed to generate. ";
            $errorCount++;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
        $errorCount++;
    }
    echo "\n";
}

echo "\nDone!\n";
echo "Total: $total\n";
echo "Success: $successCount\n";
echo "Errors: $errorCount\n";
