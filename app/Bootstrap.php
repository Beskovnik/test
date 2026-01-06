<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Media.php';

use App\Database;

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
    // Return false to let normal error handling continue (printing to screen if display_errors is on)
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
    // We want to show the debug bar even on exception if possible, but PHP might stop.
    // However, since we are in output buffering or just rendering, we might be able to print it.
    // For now, let's just dump it if possible or rely on the visible output.
    echo "<div style='background:red;color:white;padding:10px;border:1px solid darkred;'>CRITICAL EXCEPTION: " . htmlspecialchars($e->getMessage()) . "</div>";
});

session_start();

// Request ID for tracing
if (!isset($_SERVER['REQUEST_ID'])) {
    $_SERVER['REQUEST_ID'] = uniqid('req_', true);
}

$pdo = Database::connect();

// Ensure DB Schema (Simplified migration check)
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

// Helper functions for backward compatibility/ease of use in legacy files
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
