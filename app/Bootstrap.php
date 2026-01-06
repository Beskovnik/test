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

// Ensure DB Schema via Migrations
Migrator::migrate($pdo);
