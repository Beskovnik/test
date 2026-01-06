<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Media.php';
require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/Migrator.php';
// Include global helpers
require_once __DIR__ . '/Helpers.php';

use App\Database;
use App\Migrator;

// Debugging Setup
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

global $debug_log;
$debug_log = [];

set_error_handler(function ($severity, $message, $file, $line) {
    global $debug_log;
    $debug_log[] = [
        'type' => 'Error',
        'message' => $message,
        'file' => $file,
        'line' => $line
    ];
    // Return false to let standard error handler continue if needed, but we usually want to just log
    return false;
});

set_exception_handler(function ($e) {
    global $debug_log;
    $debug_log[] = [
        'type' => 'Exception',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
    error_log("Unhandled Exception: " . $e->getMessage());
    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        \App\Response::error('Internal Server Error', 'EXCEPTION', 500);
    }

    // Fallback UI for fatal errors
    if (!headers_sent()) {
        http_response_code(500);
    }

    echo "<div style='background:#1a1c25;color:#ff4757;padding:2rem;font-family:sans-serif;'>
        <h1>Critical Error</h1>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Request ID
if (!isset($_SERVER['REQUEST_ID'])) {
    $_SERVER['REQUEST_ID'] = uniqid('req_', true);
}

// Initial directory check
$baseDir = dirname(__DIR__);
$paths = [
    'data' => $baseDir . '/_data',
    'uploads' => $baseDir . '/uploads',
    'thumbs' => $baseDir . '/thumbs',
];
foreach ($paths as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

$pdo = Database::connect();

// DB Init & Schema (Using Migrator for versioning, but ensuring base tables first)
// We keep the base table creation here to ensure the system can boot even if migrations fail or are empty.
// However, to avoid duplication, we should rely on Migrator or these checks.
// For robustness, I'll keep the IF NOT EXISTS checks as a safeguard.

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        email TEXT UNIQUE,
        pass_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT "user",
        active INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NULL, -- Made nullable to match old bootstrap
        title TEXT,
        description TEXT,
        created_at INTEGER NOT NULL,
        visibility TEXT DEFAULT "public",
        share_token TEXT,
        type TEXT NOT NULL,
        file_path TEXT NOT NULL,
        thumb_path TEXT,
        preview_path TEXT,
        mime TEXT,
        size_bytes INTEGER,
        width INTEGER,
        height INTEGER,
        views INTEGER DEFAULT 0,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER NULL,
        author_name TEXT NULL, -- Added to match old bootstrap
        body TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT "visible",
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT, -- Added ID for consistency
        user_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        UNIQUE(user_id, post_id),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_user_id INTEGER,
        action TEXT,
        meta TEXT,
        created_at INTEGER
    )'
);

// Apply migrations
Migrator::migrate($pdo);
