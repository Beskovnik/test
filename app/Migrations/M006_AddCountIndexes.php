<?php
declare(strict_types=1);

namespace App\Migrations;

use PDO;

class M006_AddCountIndexes
{
    public function up(PDO $pdo): void
    {
        // Add index for likes count queries
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_likes_post_id ON likes(post_id)');

        // Add index for comments count queries
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id)');
    }
}
