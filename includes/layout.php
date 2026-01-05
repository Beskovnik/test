<?php

declare(strict_types=1);

function render_header(string $title, ?array $user, string $active = 'feed'): void
{
    $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    $isAdmin = $user && $user['role'] === 'admin';
    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="csrf-token" content="' . $csrf . '">';
    echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
    echo '<link rel="stylesheet" href="/assets/css/app.css">';
    echo '</head><body>';
    echo '<div class="app">';
    echo '<aside class="sidebar">';
    echo '<div class="logo">immich<span>gallery</span></div>';
    echo '<nav class="nav">';
    echo nav_link('feed', '/index.php', 'Slike');
    echo nav_link('videos', '/index.php?type=video', 'Video');
    echo nav_link('explore', '/index.php?tab=explore', 'Raziskuj');
    echo nav_link('albums', '/index.php?tab=albums', 'Albumi');
    echo nav_link('favorites', '/index.php?tab=favorites', 'Priljubljene');
    echo nav_link('upload', '/upload.php', 'Upload');
    if ($isAdmin) {
        echo nav_link('admin', '/admin/index.php', 'Admin');
    }
    echo '</nav>';
    echo '</aside>';
    echo '<main class="main">';
    echo '<header class="topbar">';
    echo '<form class="search" action="/index.php" method="get">';
    echo '<input type="search" name="q" placeholder="Poišči svoje fotografije" value="' . htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    echo '</form>';
    echo '<div class="top-actions">';
    echo '<a class="button" href="/upload.php">Naloži</a>';
    if ($user) {
        echo '<div class="user-menu">';
        echo '<span>' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</span>';
        echo '<a href="/logout.php">Odjava</a>';
        echo '</div>';
    } else {
        echo '<a class="link" href="/login.php">Prijava</a>';
        echo '<a class="link" href="/register.php">Registracija</a>';
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
    echo '</main></div>';
    echo '<script src="/assets/js/app.js"></script>';
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
