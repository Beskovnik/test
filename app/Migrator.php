<?php
declare(strict_types=1);

namespace App;

use PDO;

class Migrator
{
    public static function migrate(PDO $pdo): void
    {
        // 1. Ensure migrations table exists
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            migration TEXT UNIQUE,
            created_at INTEGER
        )');

        // 2. Get applied migrations
        $stmt = $pdo->query('SELECT migration FROM migrations');
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // 3. Get available migrations
        $dir = __DIR__ . '/Migrations/';
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '*.php');
        sort($files); // Ensure execution order

        foreach ($files as $file) {
            $filename = basename($file);
            // Expected class name matches filename (without extension)
            // e.g. M001_InitialSchema.php -> App\Migrations\M001_InitialSchema
            $classNameStr = pathinfo($filename, PATHINFO_FILENAME);
            $fullClassName = 'App\\Migrations\\' . $classNameStr;

            // Skip if already applied
            if (in_array($fullClassName, $applied)) {
                continue;
            }

            // Load the migration file
            require_once $file;

            if (class_exists($fullClassName)) {
                $migration = new $fullClassName();

                if (method_exists($migration, 'up')) {
                    // echo "Applying migration: $classNameStr\n";
                    // Note: We avoid echo in bootstrap unless CLI,
                    // but for now we keep it silent or log it.

                    try {
                        // Use transaction if supported (SQLite supports it)
                        if ($pdo->inTransaction()) {
                             // Should not happen if we manage transactions here
                             $pdo->commit();
                        }

                        $pdo->beginTransaction();

                        $migration->up($pdo);

                        $stmt = $pdo->prepare('INSERT INTO migrations (migration, created_at) VALUES (?, ?)');
                        $stmt->execute([$fullClassName, time()]);

                        $pdo->commit();
                    } catch (\Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        // Re-throw to halt execution on migration failure
                        throw new \Exception("Migration failed: $classNameStr. Error: " . $e->getMessage(), 0, $e);
                    }
                }
            }
        }
    }
}
