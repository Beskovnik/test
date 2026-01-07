<?php

declare(strict_types=1);

use App\Settings;
use App\Auth;

function render_header(string $title, ?array $user, string $active = 'feed'): void
{
    global $pdo, $errors;

    // Use default values for setup, avoiding DB calls if things are broken is nice but we assume DB is fine.
    $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $isAdmin = $user && $user['role'] === 'admin';

    echo '<!DOCTYPE html><html lang="sl">';
    echo '<head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="csrf-token" content="' . $csrf . '">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';

    // NEW THEME
    echo '<link rel="stylesheet" href="/assets/css/theme.css">';

    // Dynamic Theme Color Injection
    if (class_exists('App\Settings') && isset($pdo)) {
        $accent = Settings::get($pdo, 'accent_color', '#ffb84d');
        if ($accent) {
            echo '<style>:root { --accent: ' . htmlspecialchars($accent, ENT_QUOTES, 'UTF-8') . '; }</style>';
        }
    }

    // Icons
    echo '<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">';

    echo '</head><body class="ui">';

    // Error Toast
    if (!empty($errors)) {
        echo '<div class="toast show toast-error" id="error-toast">';
        echo '<div style="width:100%;">';
        echo '<div style="font-weight:bold;margin-bottom:0.5rem;display:flex;justify-content:space-between;align-items:center;">Setup Issue <button style="background:none;border:none;color:inherit;cursor:pointer;" onclick="document.getElementById(\'error-toast\').remove()">×</button></div>';
        echo '<ul style="padding-left:1rem;margin:0;">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div></div>';
    }

    echo '<div class="app">';
    echo '<div class="mobile-overlay" onclick="document.body.classList.remove(\'menu-open\')"></div>';

    // SIDEBAR
    echo '<aside class="ui-sidebar">';
    echo '<a href="/index.php" class="ui-title">Galerija <span>.</span></a>';

    echo '<nav class="ui-menu">';

    $currentNav = $_GET['nav'] ?? $active;

    // Helper closure for nav links (Pills)
    $makeLink = function($key, $href, $label) use ($currentNav) {
        $class = $currentNav === $key ? 'is-active' : '';
        return '<a class="ui-pill ' . $class . '" href="' . $href . '">' . $label . '</a>';
    };

    // Specific Order as requested
    echo $makeLink('feed', '/index.php?type=image&nav=feed', 'Moje Slike');
    echo $makeLink('videos', '/index.php?type=video&nav=videos', 'Moji Videi');
    echo $makeLink('public', '/index.php?view=public&nav=public', 'Javno');
    echo $makeLink('weather', '/weather.php', 'Vreme');
    echo $makeLink('myshares', '/my-shares.php', 'Moje Delitve');
    echo $makeLink('upload', '/upload.php', 'Upload');
    if ($isAdmin) {
        echo $makeLink('admin', '/admin/index.php', 'Admin Panel');
    }
    echo $makeLink('settings', '/settings.php', 'Pravila');

    echo '</nav>';
    echo '</aside>';

    // MAIN CONTENT
    echo '<main class="ui-main">';

    // Header
    echo '<header class="ui-header">';

    // Mobile Toggle
    echo '<button class="mobile-menu-toggle" aria-label="Menu" onclick="document.body.classList.toggle(\'menu-open\')">☰</button>';

    // Search
    echo '<form action="/index.php" method="get" style="margin:0;">';
    echo '<input class="ui-search" type="search" name="q" placeholder="Poišči..." value="' . htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    echo '</form>';

    // Top Actions
    echo '<div style="display:flex;align-items:center;gap:1rem;">';
    // Pill Gradient Button
    echo '<a class="button" style="border-radius:999px; background:linear-gradient(135deg, var(--accent) 0%, #aa66cc 100%); border:none; color:#fff; font-weight:600; padding: 0.6rem 1.4rem;" href="/upload.php"><span class="material-icons" style="font-size:1.1em; margin-right:6px; vertical-align:middle;">cloud_upload</span><span class="desktop-only" style="vertical-align:middle;">Naloži</span></a>';

    if ($user) {
        echo '<div style="font-size:0.9rem; color:var(--text-muted);">';
        echo '<span style="color:#fff;font-weight:600;">' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo ' <a href="/logout.php" style="margin-left:5px; opacity:0.7;">(Odjava)</a>';
        echo '</div>';
    } else {
        echo '<a class="button secondary" href="/login.php">Prijava</a>';
    }
    echo '</div>';

    echo '</header>';
}

function render_footer(): void
{
    echo '</main></div>'; // Close main and app

    // Keep JS
    echo '<script src="/assets/js/app.js"></script>';
    echo '<script src="/assets/js/infinite_scroll.js"></script>';

    if (defined('DEBUG') && DEBUG) {
        echo '<script src="/assets/js/debug_console.js"></script>';
        global $debug_log;
        // Simplified debug output if needed
    }

    echo '</body></html>';
}
