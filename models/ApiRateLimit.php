<?php

declare(strict_types=1);

class ApiRateLimit
{
    private static bool $tableChecked = false;

    public static function hit(string $scope, int $maxRequests, int $windowSeconds): array
    {
        self::ensureTable();

        $scopeKey = self::scopeKey($scope);
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                'SELECT request_count, TIMESTAMPDIFF(SECOND, window_started_at, NOW()) AS elapsed_seconds
                 FROM api_rate_limits
                 WHERE scope_key = :scope_key
                 FOR UPDATE'
            );
            $stmt->execute(['scope_key' => $scopeKey]);
            $row = $stmt->fetch();

            if (!$row) {
                $insert = $pdo->prepare(
                    'INSERT INTO api_rate_limits (scope_key, scope_name, request_count, window_started_at, last_request_at)
                     VALUES (:scope_key, :scope_name, 1, NOW(), NOW())'
                );
                $insert->execute([
                    'scope_key' => $scopeKey,
                    'scope_name' => self::truncateScope($scope),
                ]);
                $pdo->commit();

                return self::result(true, $maxRequests, $maxRequests - 1, $windowSeconds);
            }

            $elapsed = max(0, (int) $row['elapsed_seconds']);

            if ($elapsed >= $windowSeconds) {
                $update = $pdo->prepare(
                    'UPDATE api_rate_limits
                     SET request_count = 1, window_started_at = NOW(), last_request_at = NOW()
                     WHERE scope_key = :scope_key'
                );
                $update->execute(['scope_key' => $scopeKey]);
                $pdo->commit();

                return self::result(true, $maxRequests, $maxRequests - 1, $windowSeconds);
            }

            $count = (int) $row['request_count'];
            $retryAfter = max(1, $windowSeconds - $elapsed);

            if ($count >= $maxRequests) {
                $blocked = $pdo->prepare(
                    'UPDATE api_rate_limits
                     SET last_request_at = NOW()
                     WHERE scope_key = :scope_key'
                );
                $blocked->execute(['scope_key' => $scopeKey]);
                $pdo->commit();

                return self::result(false, $maxRequests, 0, $retryAfter);
            }

            $update = $pdo->prepare(
                'UPDATE api_rate_limits
                 SET request_count = request_count + 1, last_request_at = NOW()
                 WHERE scope_key = :scope_key'
            );
            $update->execute(['scope_key' => $scopeKey]);
            $pdo->commit();

            return self::result(true, $maxRequests, max(0, $maxRequests - $count - 1), $retryAfter);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    public static function cleanupExpired(): void
    {
        self::ensureTable();

        $ttl = max(1, API_RATE_LIMIT_WINDOW_SECONDS * 4);
        $stmt = db()->prepare(
            'DELETE FROM api_rate_limits
             WHERE last_request_at < (NOW() - INTERVAL ' . $ttl . ' SECOND)'
        );
        $stmt->execute();
    }

    private static function result(bool $allowed, int $limit, int $remaining, int $retryAfter): array
    {
        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
        ];
    }

    private static function scopeKey(string $scope): string
    {
        return hash('sha256', $scope);
    }

    private static function truncateScope(string $scope): string
    {
        return substr($scope, 0, 190);
    }

    private static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS api_rate_limits (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                scope_key CHAR(64) NOT NULL UNIQUE,
                scope_name VARCHAR(190) NOT NULL,
                request_count INT UNSIGNED NOT NULL DEFAULT 0,
                window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_request_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_api_rate_limits_last_request (last_request_at)
            ) ENGINE=InnoDB'
        );

        self::$tableChecked = true;
    }
}
