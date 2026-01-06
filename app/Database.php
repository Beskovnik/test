<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance === null) {
            $dbPath = __DIR__ . '/../_data/database.sqlite';
            $dir = dirname($dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            try {
                self::$instance = new PDO('sqlite:' . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$instance->exec('PRAGMA foreign_keys = ON');
            } catch (PDOException $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$instance;
    }
}
