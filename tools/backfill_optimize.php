<?php
/**
 * Backfill Optimization Script
 * Usage: php tools/backfill_optimize.php
 */

require_once __DIR__ . '/../app/Bootstrap.php';

use App\Database;
use App\Media;

// Disable time limit
set_time_limit(0);

echo "Starting Backfill Optimization...\n";

$pdo = Database::connect();

// Get all posts
$stmt = $pdo->query("SELECT * FROM posts ORDER BY id DESC");
$posts = $stmt->fetchAll();

$total = count($posts);
$count = 0;
$updated = 0;
$errors = 0;
$skipped = 0;

// Ensure directories
$baseDir = __DIR__ . '/../';
$optimizedDir = $baseDir . 'optimized';
$thumbsDir = $baseDir . 'uploads/thumbs';

if (!is_dir($optimizedDir)) mkdir($optimizedDir, 0777, true);
if (!is_dir($thumbsDir)) mkdir($thumbsDir, 0777, true);

foreach ($posts as $post) {
    $count++;
    $id = $post['id'];
    $type = $post['type'];
    $filePath = $post['file_path']; // uploads/original/...

    // Absolute paths
    $sourceAbs = $baseDir . $filePath;

    if (!file_exists($sourceAbs)) {
        echo "[$count/$total] ID $id: Source file missing ($filePath). Skipping.\n";
        $errors++;
        continue;
    }

    $needsUpdate = false;
    $newThumbPath = $post['thumb_path'];
    $newOptimizedPath = $post['optimized_path'] ?? null;

    // --- THUMBNAIL CHECK ---
    // Check if thumb is missing, or is "placeholder", or file doesn't exist
    // Also we want strict 480px WEBP/JPG thumbs now.
    // If old thumb was 420px (from upload.php legacy), we might want to regenerate to 480px?
    // User said: "za vsakega: če thumb... manjka ali datoteka ne obstaja, jo generira".
    // I will check existence.

    $thumbAbs = $newThumbPath ? $baseDir . $newThumbPath : null;

    // Logic: If thumb path is empty OR file missing OR it's the generic placeholder (and we have a real video/image)
    $missingThumb = empty($newThumbPath) || !file_exists($thumbAbs) || strpos($newThumbPath, 'placeholder') !== false;

    // Generate Thumb if missing
    if ($missingThumb) {
        $ext = ($type === 'video') ? 'jpg' : 'webp';
        $randomName = pathinfo($filePath, PATHINFO_FILENAME); // Reuse filename or random?
        // Better use existing file basename to avoid clutter, or MD5.
        // Current upload uses random hex. $post['file_path'] usually has random hex.
        $basename = pathinfo($filePath, PATHINFO_FILENAME);

        $thumbName = $basename . '.' . $ext;
        $targetThumbRel = 'uploads/thumbs/' . $thumbName;
        $targetThumbAbs = $baseDir . $targetThumbRel;

        echo "[$count/$total] ID $id: Generating Thumb...\n";

        $success = false;
        if ($type === 'image') {
            $success = Media::generateResized($sourceAbs, $targetThumbAbs, 480, 480, 75);
        } else {
            $success = Media::generateVideoThumb($sourceAbs, $targetThumbAbs, 480);
        }

        if ($success) {
            $newThumbPath = $targetThumbRel;
            $needsUpdate = true;
        } else {
            echo "  -> Failed to generate thumb.\n";
            $errors++;
        }
    }

    // --- OPTIMIZED CHECK (Images Only) ---
    if ($type === 'image') {
        $optimizedAbs = $newOptimizedPath ? $baseDir . $newOptimizedPath : null;
        $missingOptimized = empty($newOptimizedPath) || !file_exists($optimizedAbs);

        if ($missingOptimized) {
            $basename = pathinfo($filePath, PATHINFO_FILENAME);
            $optName = $basename . '.webp';
            $targetOptRel = 'optimized/' . $optName;
            $targetOptAbs = $baseDir . $targetOptRel;

            echo "[$count/$total] ID $id: Generating Optimized...\n";

            $success = Media::generateResized($sourceAbs, $targetOptAbs, 1920, 1920, 82);

            if ($success) {
                $newOptimizedPath = $targetOptRel;
                $needsUpdate = true;
            } else {
                echo "  -> Failed to generate optimized.\n";
                // Fallback to original
                $newOptimizedPath = $filePath;
                $needsUpdate = true;
            }
        }
    } else {
        // Video: Optimized path = Original path (no transcoding)
        if ($newOptimizedPath !== $filePath) {
            $newOptimizedPath = $filePath;
            $needsUpdate = true;
        }
    }

    // --- UPDATE DB ---
    if ($needsUpdate) {
        $sql = "UPDATE posts SET thumb_path = :thumb, optimized_path = :opt, preview_path = :prev WHERE id = :id";
        // Also update preview_path to match optimized for compatibility
        $stmtUpdate = $pdo->prepare($sql);
        $stmtUpdate->execute([
            ':thumb' => $newThumbPath,
            ':opt' => $newOptimizedPath,
            ':prev' => $newOptimizedPath, // Sync preview with optimized
            ':id' => $id
        ]);
        $updated++;
        echo "  -> Updated DB.\n";
    } else {
        $skipped++;
    }
}

echo "\nDone. Total: $total. Updated: $updated. Skipped: $skipped. Errors: $errors.\n";
