<?php

declare(strict_types=1);

use App\Settings;
use App\Auth;

function render_header(string $title, ?array $user, string $active = 'feed'): void
{
    global $pdo, $errors;
    $accentColor = Settings::get($pdo, 'accent_color', '#4b8bff');
    $pageScale = (int)Settings::get($pdo, 'page_scale', '150');
    $bgType = Settings::get($pdo, 'bg_type', 'default');
    $bgValue = Settings::get($pdo, 'bg_value', '');

    $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $isAdmin = $user && $user['role'] === 'admin';

    $bgStyle = '';
    if ($bgType === 'color') {
        $bgStyle = 'background: ' . htmlspecialchars($bgValue) . ';';
    } elseif ($bgType === 'image') {
        $bgStyle = 'background: url(' . htmlspecialchars($bgValue) . ') no-repeat center center fixed; background-size: cover;';
    }

    // Default font size 16px = 100%. Scale adjusts this percentage on html.
    $htmlStyle = "font-size: {$pageScale}%;";

    echo '<!DOCTYPE html><html lang="sl" style="' . $htmlStyle . '"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="csrf-token" content="' . $csrf . '">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="/assets/css/app.css">';
    echo '<style>:root { --accent: ' . htmlspecialchars($accentColor) . '; } body { ' . $bgStyle . ' }</style>';
    echo '</head><body>';

    // Error Toast
    if (!empty($errors)) {
        echo '<div class="error-toast" id="error-toast">';
        echo '<div class="error-toast-header">Setup Issue <button onclick="document.getElementById(\'error-toast\').remove()">×</button></div>';
        echo '<ul>';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<div class="app">';
    echo '<div class="mobile-overlay"></div>';
    echo '<aside class="sidebar">';
    echo '<a href="/index.php" class="logo" style="text-decoration:none; color:inherit;">Galerija</a>';
    echo '<nav class="nav">';
    echo nav_link('feed', '/index.php', 'Slike');
    echo nav_link('videos', '/index.php?type=video', 'Video');
    echo nav_link('upload', '/upload.php', 'Upload');
    if ($isAdmin) {
        echo nav_link('admin', '/admin/index.php', 'Admin Panel');
    }
    echo '</nav>';
    echo '</aside>';
    echo '<main class="main">';
    echo '<header class="topbar">';
    echo '<button class="mobile-menu-toggle" aria-label="Menu">☰</button>';
    echo '<form class="search" action="/index.php" method="get">';
    echo '<input type="search" name="q" placeholder="Poišči..." value="' . htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    echo '</form>';
    echo '<div class="top-actions">';
    echo '<button class="button icon-only" id="theme-toggle" aria-label="Toggle Theme">🌙</button>';
    echo '<a class="button" href="/upload.php">Naloži</a>';
    if ($user) {
        echo '<div class="user-menu">';
        echo '<span class="user-name">' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<a href="/logout.php">Odjava</a>';
        echo '</div>';
    } else {
        echo '<a class="link" href="/login.php">Prijava</a>';
        echo '<a class="button small" href="/register.php">Registracija</a>';
    }
    echo '</div>';
    echo '</header>';
}

function nav_link(string $key, string $href, string $label): string
{
    $activeKey = $_GET['nav'] ?? 'feed';
    $class = $activeKey === $key ? 'active' : '';
    $separator = str_contains($href, '?') ? '&' : '?';
    return '<a class="nav-link ' . $class . '" href="' . $href . $separator . 'nav=' . $key . '">' . $label . '</a>';
}

function render_footer(): void
{
    global $pdo;
    echo '</main></div>';
    echo '<script src="/assets/js/app.js"></script>';
    echo '<script src="/assets/js/infinite_scroll.js"></script>';

    // Inject Debug Console for Admins
    $user = Auth::user();
    if ($user && $user['role'] === 'admin') {
        echo '<script src="/assets/js/debug_console.js"></script>';
    }

    // DEBUG BAR
    global $debug_log;
    if (!empty($debug_log)) {
        echo '<div id="debug-bar" style="position:fixed; bottom:0; left:0; right:0; background:rgba(0,0,0,0.9); color:#0f0; padding:10px; z-index:9999; max-height:200px; overflow-y:auto; font-family:monospace; border-top: 2px solid #0f0;">';
        echo '<div style="font-weight:bold; border-bottom:1px solid #333; margin-bottom:5px;">DEBUG INFO</div>';
        foreach ($debug_log as $log) {
            echo '<div style="margin-bottom:2px;">';
            echo '<span style="color:#ff0000;">[' . htmlspecialchars($log['type']) . ']</span> ';
            echo htmlspecialchars($log['message']) . ' ';
            echo '<span style="color:#888;">(' . htmlspecialchars($log['file']) . ':' . $log['line'] . ')</span>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '</body></html>';
}

function render_flash(?array $flash): void
{
    if (!$flash) {
        return;
    }
    $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
    echo '<div class="flash ' . $type . '">' . $message . '</div>';
}
