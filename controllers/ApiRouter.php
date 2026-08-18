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
            [$routeMethod, $pattern, $handler, $requiresAuth] = $route;

            if (!preg_match('#^' . $pattern . '$#', $path, $matches)) {
                continue;
            }

            $allowedMethods[] = $routeMethod;
            if ($routeMethod !== $method) {
                continue;
            }

            if ($requiresAuth && !ApiAuth::authenticate()) {
                ApiResponse::error('unauthenticated', 'Autenticacao obrigatoria.', 401);
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
            ApiResponse::error('method_not_allowed', 'Metodo HTTP nao permitido para este endpoint.', 405);
        }

        ApiResponse::error('not_found', 'Endpoint da API nao encontrado.', 404);
    }

    private static function requestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return '/' . trim((string) $path, '/');
    }
}
