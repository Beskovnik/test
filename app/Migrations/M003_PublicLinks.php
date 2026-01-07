<?php
declare(strict_types=1);

namespace App\Migrations;

use PDO;

class M003_PublicLinks
{
    public function up(PDO $pdo): void
    {
        $columns = $pdo->query("PRAGMA table_info(posts)")->fetchAll(PDO::FETCH_ASSOC);

        $hasIsPublic = false;
        $hasPublicToken = false;

        foreach ($columns as $col) {
            if ($col['name'] === 'is_public') {
                $hasIsPublic = true;
            }
            if ($col['name'] === 'public_token') {
                $hasPublicToken = true;
            }
        }

        if (!$hasIsPublic) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN is_public INTEGER DEFAULT 0');
        }

        if (!$hasPublicToken) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN public_token TEXT');
            $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_posts_public_token ON posts(public_token)');
        }
    }
}
