<?php
declare(strict_types=1);

use App\Settings;

/**
 * Global Helper Functions
 */

if (!function_exists('app_pdo')) {
    // Defined in Bootstrap.php usually, but here for reference
    // function app_pdo(): PDO { return \App\Database::connect(); }
}

function app_config(): array
{
    // Try to get settings from DB if possible, otherwise use defaults
    $pdo = \App\Database::connect();

    // Default config with values from App\Settings or hardcoded defaults
    return [
        'max_image_size' => (int)(Settings::get($pdo, 'max_image_gb', '50') * 1024 * 1024 * 1024),
        'max_video_size' => (int)(Settings::get($pdo, 'max_video_gb', '50') * 1024 * 1024 * 1024),
        'max_files_per_upload' => (int)Settings::get($pdo, 'max_files_per_upload', '100'),
        'allowed_images' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'allowed_videos' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska'],
        'blocked_exts' => ['php', 'phtml', 'phar', 'js', 'html', 'htm', 'exe', 'sh'],
    ];
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function render_flash(?array $flash): void
{
    if ($flash) {
        $type = htmlspecialchars($flash['type']);
        $msg = htmlspecialchars($flash['message']);
        $icon = $type === 'success' ? 'check_circle' : 'error';
        $id = uniqid('toast_');
        // Simplified HTML relying on CSS classes
        echo <<<HTML
        <div id="{$id}" class="toast show toast-{$type}">
            <span class="material-icons">{$icon}</span>
            <span>{$msg}</span>
        </div>
        <script>
            setTimeout(() => document.getElementById('{$id}')?.classList.remove('show'), 3000);
            setTimeout(() => document.getElementById('{$id}')?.remove(), 3500);
        </script>
HTML;
    }
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// CSRF Helpers

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = md5(uniqid((string)rand(), true));
        }
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            \App\Response::error('Invalid CSRF Token', 'CSRF_ERROR', 403);
        }
        http_response_code(403);
        die('Invalid CSRF Token');
    }
}
