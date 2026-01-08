<?php
declare(strict_types=1);

namespace App\Migrations;

use PDO;

class M006_AddCountIndexes
{
    public function up(PDO $pdo): void
    {
        // Add indexes to optimize count subqueries in feed
        // These significantly reduce the cost of counting likes and comments per post
        // from O(N) to O(log N) where N is total likes/comments.

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_likes_post_id ON likes(post_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id)');
    }
}
