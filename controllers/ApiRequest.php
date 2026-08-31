<?php

declare(strict_types=1);

class ApiRequest
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    public static function string(string $key, string $default = ''): string
    {
        return trim((string) ($_GET[$key] ?? $default));
    }

    public static function bool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $_GET)) {
            return $default;
        }

        return filter_var($_GET[$key], FILTER_VALIDATE_BOOLEAN);
    }

    public static function json(): array
    {
        $rawBody = file_get_contents('php://input');

        if ($rawBody === false || trim($rawBody) === '') {
            return [];
        }

        if (strlen($rawBody) > API_MAX_JSON_BYTES) {
            ApiResponse::error('payload_too_large', 'O corpo da requisicao excede o tamanho permitido.', 413);
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            ApiResponse::error('invalid_json', 'O corpo da requisicao deve ser um JSON valido.', 400, [
                'json_error' => json_last_error_msg(),
            ]);
        }

        return $decoded;
    }

    public static function csrfToken(): ?string
    {
        $headers = [
            $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null,
            $_SERVER['HTTP_X_CSRFTOKEN'] ?? null,
            $_POST['csrf_token'] ?? null,
        ];

        foreach ($headers as $token) {
            if (is_string($token) && trim($token) !== '') {
                return trim($token);
            }
        }

        $payload = self::json();
        $token = $payload['csrf_token'] ?? null;

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    public static function pagination(): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, (int) ($_GET['per_page'] ?? self::DEFAULT_PER_PAGE));
        $perPage = min($perPage, self::MAX_PER_PAGE);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    public static function paginationMeta(int $total, array $pagination): array
    {
        $perPage = (int) $pagination['per_page'];
        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'page' => (int) $pagination['page'],
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'has_more' => (int) $pagination['page'] < $lastPage,
        ];
    }

    public static function filled(array $values): array
    {
        return array_filter($values, static fn ($value): bool => (string) $value !== '');
    }
}
