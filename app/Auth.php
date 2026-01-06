<?php
declare(strict_types=1);

namespace App;

use PDO;

class Auth
{
    public static function user(): ?array
    {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND active = 1');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            // Check if expecting JSON
            if (self::wantsJson()) {
                Response::error('Prijava je potrebna', 'UNAUTHORIZED', 401);
            }
            header('Location: /login.php');
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if ($user['role'] !== 'admin') {
            if (self::wantsJson()) {
                Response::error('Dostop zavrnjen', 'FORBIDDEN', 403);
            }
            http_response_code(403);
            die('Forbidden');
        }
        return $user;
    }

    private static function wantsJson(): bool
    {
        return isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    }
}
