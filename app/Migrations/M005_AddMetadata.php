<?php
namespace App\Migrations;

use PDO;

class M005_AddMetadata extends \stdClass
{
    public function up(PDO $pdo): void
    {
        // Check if columns exist to avoid error
        $stmt = $pdo->query("PRAGMA table_info(posts)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

        if (!in_array('photo_taken_at', $columns)) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN photo_taken_at INTEGER DEFAULT NULL');
        }

        if (!in_array('exif_json', $columns)) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN exif_json TEXT DEFAULT NULL');
        }

        // Add index for better sorting performance
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_photo_taken_at ON posts (photo_taken_at)');
    }
}
