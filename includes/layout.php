<?php

declare(strict_types=1);

use App\Settings;
use App\Auth;

function render_header(string $title, ?array $user, string $active = 'feed'): void
{
    // Need global $pdo for settings and $errors for bootstrap setup warnings.
    global $pdo, $errors;

    $accentColor = Settings::get($pdo, 'accent_color', '#4b8bff');
    // Admin Setting UI Scale
    $uiScale = Settings::get($pdo, 'ui_scale', '1.0');

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

    echo '<!DOCTYPE html><html lang="sl" style="font-size: 16px;">';
    // ^ Base 16px, real size handled by var(--ui-final-scale) calc in CSS on body or html
    // Wait, prompt said: "Na resize ... posodobi style: --ui-auto-scale. Nato izračunaj --ui-final-scale ali direktno nastavi --ui-auto-scale in v CSS množiš"
    // So I will inject --ui-scale here.

    echo '<head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="csrf-token" content="' . $csrf . '">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="/assets/css/app.css">';
    echo '<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">';

    // Inject Variables
    // Default auto-scale to 1.0 (JS will update it).
    // Formula: final = scale * auto
    echo '<style>';
    echo ':root { ';
    echo '--accent: ' . htmlspecialchars($accentColor) . '; ';
    echo '--ui-scale: ' . htmlspecialchars($uiScale) . '; ';
    echo '--ui-auto-scale: 1.0; ';
    echo '}';

    // Apply scaling to html font-size
    echo 'html { font-size: calc(16px * var(--ui-scale) * var(--ui-auto-scale)); }';

    echo 'body { ' . $bgStyle . ' }';
    echo '</style>';

    echo '</head><body>';

    // Error Toast
    if (!empty($errors)) {
        echo '<div class="toast show" id="error-toast" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">';
        echo '<div class="toast-body error-toast" style="padding: 1rem 1.5rem; border-radius: 1rem; background: var(--panel); backdrop-filter: blur(16px); border: 1px solid var(--border); color: #fff;">';
        echo '<div style="font-weight:bold;margin-bottom:0.5rem;display:flex;justify-content:space-between;align-items:center;">Setup Issue <button style="background:none;border:none;color:inherit;cursor:pointer;" onclick="document.getElementById(\'error-toast\').remove()">×</button></div>';
        echo '<ul style="padding-left:1rem;margin:0;">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        echo '</ul></div></div>';
    }

    echo '<div class="app">';
    echo '<div class="mobile-overlay"></div>';
    echo '<aside class="sidebar">';
    echo '<a href="/index.php" class="logo" style="text-decoration:none; color:inherit;">Galerija <span>.</span></a>';
    echo '<nav class="nav">';
    // Logic for active class
    $currentNav = $_GET['nav'] ?? $active;

    // Helper closure for nav links
    $makeLink = function($key, $href, $label) use ($currentNav) {
        $class = $currentNav === $key ? 'active' : '';
        // Append nav param if not present (simple check)
        // If href already has params, append &nav=key, else ?nav=key
        // However, standard links usually hardcode the destination.
        // Let's stick to the previous logic but clearer.
        return '<a class="nav-link ' . $class . '" href="' . $href . '">' . $label . '</a>';
    };

    echo $makeLink('feed', '/index.php', 'Moje Slike');
    echo $makeLink('videos', '/index.php?type=video&nav=videos', 'Moji Videi');
    echo $makeLink('public', '/index.php?view=public&nav=public', 'Javno');
    echo $makeLink('weather', '/weather.php', 'Vreme');
    echo $makeLink('myshares', '/my-shares.php', 'Moje Delitve');
    echo $makeLink('upload', '/upload.php', 'Upload');
    if ($isAdmin) {
        echo $makeLink('admin', '/admin/index.php', 'Admin Panel');
    }

    // Bottom nav items
    echo '<div style="margin-top:auto; padding-top:1rem; border-top:1px solid var(--border);">';
    echo $makeLink('settings', '/settings.php', 'Pravila');
    echo '</div>';

    echo '</nav>';
    echo '</aside>';
    echo '<main class="main">';
    echo '<header class="topbar">';
    echo '<button class="mobile-menu-toggle" aria-label="Menu" style="background:none;border:none;color:white;font-size:1.5rem;margin-right:1rem;cursor:pointer;">☰</button>';
    echo '<form class="search" action="/index.php" method="get">';
    echo '<input type="search" name="q" placeholder="Poišči..." value="' . htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    echo '</form>';
    echo '<div class="top-actions">';
    // echo '<button class="button icon-only" id="theme-toggle" aria-label="Toggle Theme">🌙</button>'; // JS handles this
    echo '<a class="button" href="/upload.php"><span class="material-icons" style="font-size:1.2rem;margin-right:0.5rem;">cloud_upload</span>Naloži</a>';
    if ($user) {
        echo '<div class="user-menu">';
        echo '<span class="user-name">' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<a href="/logout.php" style="color:var(--muted);font-size:0.9rem;margin-left:0.5rem;">(Odjava)</a>';
        echo '</div>';
    } else {
        echo '<a class="nav-link" href="/login.php" style="padding:0.5rem 1rem;">Prijava</a>';
        echo '<a class="button small" href="/register.php" style="padding:0.5rem 1rem;">Registracija</a>';
    }
    echo '</div>';
    echo '</header>';
}

function render_footer(): void
{
    echo '</main></div>';
    echo '<script src="/assets/js/app.js"></script>';
    // Infinite scroll is page specific, but let's include it if needed or check existing.
    // It's usually better to include it only on index.php.
    // But since this is a global footer, we'll leave it out and let pages include specific scripts,
    // OR include it and let it init only if element exists.
    // Existing code included it globally.
    echo '<script src="/assets/js/infinite_scroll.js"></script>';

    // Inject Debug Console if DEBUG is enabled
    if (defined('DEBUG') && DEBUG) {
        echo '<script src="/assets/js/debug_console.js"></script>';

        // DEBUG BAR (Server-side logs)
        global $debug_log;
        if (!empty($debug_log)) {
            echo '<div id="debug-bar" style="position:fixed; bottom:0; left:0; right:0; background:rgba(0,0,0,0.9); color:#0f0; padding:10px; z-index:9999; max-height:200px; overflow-y:auto; font-family:monospace; border-top: 2px solid #0f0;">';
            echo '<div style="font-weight:bold; border-bottom:1px solid #333; margin-bottom:5px;">DEBUG INFO</div>';
            foreach ($debug_log as $log) {
                echo '<div style="margin-bottom:2px;">';
                echo '<span style="color:#ff0000;">[' . htmlspecialchars((string)$log['type']) . ']</span> ';
                echo htmlspecialchars((string)$log['message']) . ' ';
                echo '<span style="color:#888;">(' . htmlspecialchars((string)$log['file']) . ':' . $log['line'] . ')</span>';
                echo '</div>';
            }
            echo '</div>';
        }
    }

    echo '</body></html>';
}
