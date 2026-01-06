<?php
declare(strict_types=1);

namespace App;

class Response
{
    public static function json(array $data, int $status = 200, ?string $requestId = null): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        // Ensure requestId is included if provided or exists in global state
        if ($requestId) {
            $data['request_id'] = $requestId;
        } elseif (isset($_SERVER['REQUEST_ID'])) {
            $data['request_id'] = $_SERVER['REQUEST_ID'];
        }

        echo json_encode($data, JSON_THROW_ON_ERROR);
        exit;
    }

    public static function error(string $message, string $code = 'ERROR', int $status = 400): void
    {
        self::json([
            'ok' => false,
            'error' => $message,
            'code' => $code
        ], $status);
    }
}
