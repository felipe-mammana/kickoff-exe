<?php

declare(strict_types=1);

class ApiRouter
{
    public static function isApiRequest(): bool
    {
        return strpos(self::requestPath(), '/api/') === 0;
    }

    public static function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = self::requestPath();
        $routes = require BASE_PATH . '/config/api_routes.php';
        $allowedMethods = [];

        foreach ($routes as $route) {
            [$routeMethod, $pattern, $handler, $requiresAuth, $requiresAdmin] = array_pad($route, 5, false);

            if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }

            $allowedMethods[] = $routeMethod;
            if ($routeMethod !== $method) {
                continue;
            }

            if ($requiresAuth && !ApiAuth::authenticate()) {
                self::enforceRateLimit(self::publicRateLimitScope($method, $path), API_RATE_LIMIT_PUBLIC_MAX_REQUESTS);
                ApiResponse::error('unauthenticated', 'Autenticação obrigatória.', 401);
            }

            self::enforceRateLimit(
                $requiresAuth ? self::authenticatedRateLimitScope($method, $path) : self::publicRateLimitScope($method, $path),
                $requiresAuth ? API_RATE_LIMIT_AUTH_MAX_REQUESTS : API_RATE_LIMIT_PUBLIC_MAX_REQUESTS
            );

            if ($requiresAuth && self::requiresCsrf($method) && ApiAuth::usesSession()) {
                $token = ApiRequest::csrfToken();
                if (!csrf_token_is_valid($token)) {
                    ApiResponse::error('csrf_required', 'Token CSRF obrigatório para mutações autenticadas por sessão.', 419);
                }
            }

            if ($requiresAdmin && !ApiAuth::isAdmin()) {
                ApiResponse::error('forbidden', 'Acesso restrito a administradores.', 403);
            }

            $params = array_filter(
                $matches,
                static fn ($key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY
            );

            [$class, $action] = $handler;
            $class::$action($params);
            return;
        }

        if ($allowedMethods) {
            header('Allow: ' . implode(', ', array_unique($allowedMethods)));
            ApiResponse::error('method_not_allowed', 'Método HTTP não permitido para este endpoint.', 405);
        }

        ApiResponse::error('not_found', 'Endpoint da API não encontrado.', 404);
    }

    private static function requestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return '/' . trim((string) $path, '/');
    }

    private static function requiresCsrf(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private static function enforceRateLimit(string $scope, int $maxRequests): void
    {
        $result = ApiRateLimit::hit($scope, $maxRequests, API_RATE_LIMIT_WINDOW_SECONDS);

        header('X-RateLimit-Limit: ' . $result['limit']);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['retry_after']);

        if ($result['allowed']) {
            return;
        }

        header('Retry-After: ' . $result['retry_after']);
        ApiResponse::error(
            'rate_limited',
            'Muitas requisicoes para a API. Aguarde antes de tentar novamente.',
            429,
            ['retry_after_seconds' => $result['retry_after']]
        );
    }

    private static function authenticatedRateLimitScope(string $method, string $path): string
    {
        $token = ApiAuth::token();
        if ($token) {
            return 'api:token:' . (int) $token['id'];
        }

        $user = ApiAuth::user();
        if ($user) {
            return 'api:user:' . (int) $user['id'];
        }

        return self::publicRateLimitScope($method, $path);
    }

    private static function publicRateLimitScope(string $method, string $path): string
    {
        return 'api:public:' . self::clientIp() . ':' . $method . ':' . $path;
    }

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
