<?php
declare(strict_types=1);

namespace App\Migrations;

use PDO;

class M004_OptimizedPath
{
    public function up(PDO $pdo): void
    {
        // Add optimized_path column to posts table if it doesn't exist
        try {
            $pdo->exec('ALTER TABLE posts ADD COLUMN optimized_path TEXT');
        } catch (\PDOException $e) {
            // Ignore error if column already exists (SQLite doesn't support IF NOT EXISTS for ADD COLUMN)
            // But good to log it or silence it if it's just "duplicate column"
        }
    }
}
