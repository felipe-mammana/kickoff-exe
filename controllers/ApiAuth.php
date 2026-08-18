<?php

declare(strict_types=1);

class ApiAuth
{
    private static ?array $user = null;
    private static ?array $token = null;

    public static function authenticate(): ?array
    {
        if (current_user()) {
            self::$user = current_user();

            return self::$user;
        }

        $plainToken = self::bearerToken();
        if ($plainToken === null) {
            return null;
        }

        $token = ApiToken::findActiveByPlainToken($plainToken);
        if (!$token) {
            return null;
        }

        ApiToken::markUsed((int) $token['id']);
        self::$token = $token;
        self::$user = [
            'id' => (int) $token['user_id'],
            'name' => (string) $token['user_name'],
            'email' => (string) $token['user_email'],
            'is_admin' => (int) $token['is_admin'],
        ];

        return self::$user;
    }

    public static function user(): ?array
    {
        return self::$user ?: current_user();
    }

    public static function token(): ?array
    {
        return self::$token;
    }

    private static function bearerToken(): ?string
    {
        $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? '');
        }

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
