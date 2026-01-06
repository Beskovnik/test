<?php
declare(strict_types=1);

namespace App;

use PDO;

class Settings
{
    public static function get(PDO $pdo, string $key, ?string $default = null): ?string
    {
        $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = :key');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    public static function set(PDO $pdo, string $key, ?string $value): void
    {
        $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $stmt->execute([':key' => $key, ':value' => $value]);
    }
}
