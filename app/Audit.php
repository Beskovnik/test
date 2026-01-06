<?php
declare(strict_types=1);

namespace App;

use PDO;

class Audit
{
    public static function ensureTableExists(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_user_id INTEGER,
            action TEXT,
            meta TEXT,
            created_at INTEGER
        )');
    }

    public static function log(PDO $pdo, int $adminId, string $action, string $meta = ''): void
    {
        // Table existence is guaranteed by Bootstrap.php
        $stmt = $pdo->prepare('INSERT INTO audit_logs (admin_user_id, action, meta, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$adminId, $action, $meta, time()]);
    }

    public static function getLogs(PDO $pdo, int $limit = 20): array
    {
        $stmt = $pdo->prepare('SELECT audit_logs.*, users.username FROM audit_logs LEFT JOIN users ON audit_logs.admin_user_id = users.id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
