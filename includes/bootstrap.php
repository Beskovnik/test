<?php
declare(strict_types=1);

session_start();

$baseDir = dirname(__DIR__);
$paths = [
    'data' => $baseDir . '/_data',
    'uploads' => $baseDir . '/uploads',
    'thumbs' => $baseDir . '/thumbs',
];

$errors = [];

$required_extensions = ['gd', 'pdo_sqlite'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Missing PHP extension: {$ext}";
    }
}

foreach ($paths as $key => $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_writable($dir)) {
        $errors[] = "Directory not writable: {$dir}";
    }
}

$dbPath = $paths['data'] . '/database.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec('PRAGMA foreign_keys = ON');

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
        user_id INTEGER NULL,
        title TEXT,
        description TEXT,
        created_at INTEGER NOT NULL,
        visibility TEXT NOT NULL DEFAULT "public",
        share_token TEXT UNIQUE NOT NULL,
        type TEXT NOT NULL,
        file_path TEXT NOT NULL,
        mime TEXT NOT NULL,
        size_bytes INTEGER NOT NULL,
        width INTEGER NULL,
        height INTEGER NULL,
        duration_sec INTEGER NULL,
        thumb_path TEXT NOT NULL,
        views INTEGER NOT NULL DEFAULT 0,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )'
);

// Add views column if missing (migration)
$columns = $pdo->query("PRAGMA table_info(posts)")->fetchAll();
$hasViews = false;
foreach ($columns as $col) {
    if ($col['name'] === 'views') {
        $hasViews = true;
        break;
    }
}
if (!$hasViews) {
    $pdo->exec('ALTER TABLE posts ADD COLUMN views INTEGER NOT NULL DEFAULT 0');
}
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        created_at INTEGER NOT NULL,
        UNIQUE(post_id, user_id),
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
    )'
);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER NULL,
        author_name TEXT NULL,
        body TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT "visible",
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )'
);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )'
);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_user_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        meta TEXT,
        created_at INTEGER NOT NULL,
        FOREIGN KEY(admin_user_id) REFERENCES users(id) ON DELETE CASCADE
    )'
);

// Seed default admin user
$stmt = $pdo->query('SELECT COUNT(*) FROM users');
if ((int)$stmt->fetchColumn() === 0) {
    $stmt = $pdo->prepare('INSERT INTO users (username, pass_hash, role, active, created_at) VALUES (:u, :p, :r, 1, :c)');
    $stmt->execute([
        ':u' => 'koble',
        ':p' => password_hash('matiden1', PASSWORD_DEFAULT),
        ':r' => 'admin',
        ':c' => time(),
    ]);
}

function setting_get(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    if (!$row) {
        return $default;
    }
    return $row['value'];
}

function setting_set(PDO $pdo, string $key, ?string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function app_config(): array
{
    return [
        // 50 GB limit
        'max_image_size' => 50 * 1024 * 1024 * 1024,
        'allowed_images' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'allowed_videos' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'],
        'blocked_exts' => ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'exe', 'sh'],
    ];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}
