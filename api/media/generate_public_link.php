<?php
require_once __DIR__ . '/../../app/Bootstrap.php';

use App\Auth;
use App\Response;
use App\Database;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Metoda ni dovoljena', 'METHOD_NOT_ALLOWED', 405);
}

$user = Auth::user();
if (!$user) {
    Response::error('Dostop zavrnjen', 'UNAUTHORIZED', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    Response::error('Manjka ID', 'MISSING_ID');
}

$pdo = Database::connect();
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
$stmt->execute([':id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    Response::error('Medij ni najden', 'NOT_FOUND', 404);
}

// Access Control: Owner or Admin
$isOwner = ($post['user_id'] == $user['id'] || $post['owner_user_id'] == $user['id']);
$isAdmin = ($user['role'] === 'admin');

if (!$isOwner && !$isAdmin) {
    Response::error('Nimate pravic za to dejanje', 'FORBIDDEN', 403);
}

// Logic:
// 1. Generate token if not exists (or regenerate? User implies just get one).
// 2. Set is_public = 1.
// 3. Return URL.

$token = $post['public_token'];
if (empty($token)) {
    // Generate 64-char hex string (32 bytes)
    $token = bin2hex(random_bytes(32));
}

// Update DB
// We also set visibility to 'public' to ensure consistency with the system's "Public" status
$update = $pdo->prepare('UPDATE posts SET is_public = 1, public_token = :token, visibility = "public" WHERE id = :id');
$update->execute([':token' => $token, ':id' => $id]);

// Construct absolute URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
// If there's a port map, standard headers usually handle it, but sometimes we need X-Forwarded-Host if behind proxy
// For now, HTTP_HOST is the safest bet for the container's perspective if not proxied, or if proxied correctly.
$publicUrl = $protocol . $host . '/public.php?t=' . $token;

Response::json([
    'success' => true,
    'public_token' => $token,
    'public_url' => $publicUrl,
    'is_public' => true
]);
