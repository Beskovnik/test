<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Media.php';
require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/Migrator.php';

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
    echo "<div style='background:red;color:white;padding:10px;border:1px solid darkred;'>CRITICAL EXCEPTION: " . htmlspecialchars($e->getMessage()) . "</div>";

    // Debug Bar on Exception
    global $debug_log; // Ensure scope access
    if (!empty($debug_log)) {
        echo '<div id="debug-bar" style="position:fixed; bottom:0; left:0; right:0; background:rgba(0,0,0,0.9); color:#0f0; padding:10px; z-index:9999; max-height:200px; overflow-y:auto; font-family:monospace; border-top: 2px solid #0f0;">';
        echo '<div style="font-weight:bold; border-bottom:1px solid #333; margin-bottom:5px;">DEBUG INFO</div>';
        foreach ($debug_log as $log) {
            echo '<div style="margin-bottom:2px;">';
            echo '<span style="color:#ff0000;">[' . htmlspecialchars($log['type'] ?? 'UNKNOWN') . ']</span> ';
            echo htmlspecialchars($log['message'] ?? '') . ' ';
            echo '<span style="color:#888;">(' . htmlspecialchars($log['file'] ?? '?') . ':' . ($log['line'] ?? '?') . ')</span>';
            echo '</div>';
        }
        echo '</div>';
    }
});

session_start();

// Request ID
if (!isset($_SERVER['REQUEST_ID'])) {
    $_SERVER['REQUEST_ID'] = uniqid('req_', true);
}

$pdo = Database::connect();

// Ensure DB Schema
// Users
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

// Posts
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
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
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )'
);

// Comments
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        body TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )'
);

// Likes
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS likes (
        user_id INTEGER NOT NULL,
        post_id INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        PRIMARY KEY (user_id, post_id),
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
    )'
);

// Settings
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )'
);

// Audit Log
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS audit_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_user_id INTEGER,
        action TEXT,
        meta TEXT,
        created_at INTEGER
    )'
);
// Ensure DB Schema via Migrations
Migrator::migrate($pdo);

// Helpers
function app_pdo(): PDO {
    return Database::connect();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            \App\Response::error('Invalid CSRF Token', 'CSRF_ERROR', 403);
        }
        die('Invalid CSRF Token');
    }
}
