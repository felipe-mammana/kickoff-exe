<?php

declare(strict_types=1);

class ApiResponse
{
    public static function ok($data = null, array $meta = [], int $status = 200): void
    {
        self::json([
            'ok' => true,
            'data' => $data,
            'meta' => (object) $meta,
        ], $status);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): void
    {
        self::json([
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => (object) $details,
            ],
        ], $status);
    }

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
