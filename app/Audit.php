<?php
declare(strict_types=1);

namespace App;

use PDO;

class Audit
{
    public static function log(PDO $pdo, int $adminId, string $action, string $meta = ''): void
    {
        $stmt = $pdo->prepare('INSERT INTO audit_log (admin_user_id, action, meta, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$adminId, $action, $meta, time()]);
    }
}
