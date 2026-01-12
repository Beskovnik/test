<?php

namespace App\Migrations;

use PDO;

class M006_AddCountIndexes
{
    public function up(PDO $pdo): void
    {
        // Add indexes to optimize counting queries for likes and comments
        // These are critical for the main feed performance (N+1 query reduction strategy)

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_likes_post_id ON likes(post_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id)");
    }
}
