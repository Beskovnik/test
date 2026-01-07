<?php
// tools/backfill_thumbnails.php

if (php_sapi_name() !== 'cli') {
    die("CLI only");
}

require __DIR__ . '/../app/Bootstrap.php';

use App\Media;
use App\Settings;

echo "Starting thumbnail backfill...\n";

// Get Settings
$thumbW = (int)Settings::get($pdo, 'thumb_width', '480');
$thumbH = (int)Settings::get($pdo, 'thumb_height', '480');
$quality = (int)Settings::get($pdo, 'thumb_quality', '80');

$sql = "SELECT * FROM posts WHERE type = 'image'";
$stmt = $pdo->query($sql);
$posts = $stmt->fetchAll();

$rootDir = dirname(__DIR__);
$count = 0;

foreach ($posts as $post) {
    $id = $post['id'];
    $file = $post['file_path']; // e.g., uploads/original/xyz.jpg
    $thumb = $post['thumb_path']; // e.g. uploads/thumbs/xyz.webp

    // Absolute Paths
    $sourcePath = $rootDir . '/' . $file;

    if (!file_exists($sourcePath)) {
        echo "Skipping ID $id: Source file not found ($sourcePath)\n";
        continue;
    }

    // Check if thumb needs generation
    // Criteria:
    // 1. thumb_path is empty
    // 2. thumb_path equals file_path (legacy behavior)
    // 3. thumb file does not exist

    $needsGen = false;
    $targetThumbPath = '';

    if (empty($thumb) || $thumb === $file) {
        $needsGen = true;
        // Generate new thumb name
        $name = pathinfo($file, PATHINFO_FILENAME);
        $targetThumbPath = 'uploads/thumbs/' . $name . '.webp';
    } else {
        $targetThumbPath = $thumb;
        if (!file_exists($rootDir . '/' . $targetThumbPath)) {
            $needsGen = true;
        }
    }

    if ($needsGen) {
        echo "Generating thumbnail for ID $id...\n";

        // Ensure dir
        $fullTarget = $rootDir . '/' . $targetThumbPath;
        $dir = dirname($fullTarget);
        if (!is_dir($dir)) mkdir($dir, 0777, true);

        $success = Media::generateResized($sourcePath, $fullTarget, $thumbW, $thumbH, $quality);

        if ($success) {
            $update = $pdo->prepare("UPDATE posts SET thumb_path = :path WHERE id = :id");
            $update->execute([':path' => $targetThumbPath, ':id' => $id]);
            echo "  -> Success: $targetThumbPath\n";
            $count++;
        } else {
            echo "  -> Failed to generate thumbnail.\n";
        }
    } else {
        // echo "Skipping ID $id: Thumbnail OK.\n";
    }
}

echo "Done. Generated $count thumbnails.\n";
