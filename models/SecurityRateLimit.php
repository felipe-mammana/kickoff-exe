<?php

declare(strict_types=1);

class SecurityRateLimit
{
    private static bool $tableChecked = false;

    public static function hit(string $purpose, int $userId, int $limit = 5, int $windowSeconds = 600): array
    {
        self::ensureTable();
        $pdo = db();
        $key = self::key($purpose, $userId);
        $pdo->beginTransaction();

        try {
            // Create and lock the bucket atomically, including concurrent first requests.
            $stmt = $pdo->prepare(
                'INSERT INTO security_rate_limits (scope_key, attempts, expires_at)
                 VALUES (:key, 0, DATE_ADD(NOW(), INTERVAL :seconds SECOND))
                 ON DUPLICATE KEY UPDATE scope_key = VALUES(scope_key)'
            );
            $stmt->execute(['key' => $key, 'seconds' => $windowSeconds]);
            $stmt = $pdo->prepare(
                'SELECT attempts, TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS remaining
                 FROM security_rate_limits WHERE scope_key = :key FOR UPDATE'
            );
            $stmt->execute(['key' => $key]);
            $row = $stmt->fetch();
            $expired = (int) $row['remaining'] <= 0;
            $attempts = $expired ? 0 : (int) $row['attempts'];
            $retryAfter = $expired ? $windowSeconds : max(1, (int) $row['remaining']);
            $allowed = $attempts < $limit;

            if ($allowed) {
                $stmt = $pdo->prepare(
                    'UPDATE security_rate_limits SET attempts = :attempts,
                     expires_at = IF(:expired = 1, DATE_ADD(NOW(), INTERVAL :seconds SECOND), expires_at)
                     WHERE scope_key = :key'
                );
                $stmt->execute([
                    'attempts' => $attempts + 1, 'expired' => $expired ? 1 : 0,
                    'seconds' => $windowSeconds, 'key' => $key,
                ]);
            }
            $pdo->commit();

            return ['allowed' => $allowed, 'remaining' => max(0, $limit - $attempts - ($allowed ? 1 : 0)), 'retry_after' => $retryAfter];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function emailSend(int $userId): array
    {
        $cooldown = self::hit('email-send', $userId, 1, 60);
        return $cooldown['allowed'] ? self::hit('email-send-window', $userId) : $cooldown;
    }

    public static function clear(string $purpose, int $userId): void
    {
        self::ensureTable();
        $stmt = db()->prepare('DELETE FROM security_rate_limits WHERE scope_key = :key');
        $stmt->execute(['key' => self::key($purpose, $userId)]);
    }

    public static function retryAfter(string $purpose, int $userId, int $limit = 5): int
    {
        self::ensureTable();
        $stmt = db()->prepare(
            'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM security_rate_limits
             WHERE scope_key = :key AND attempts >= :limit'
        );
        $stmt->execute(['key' => self::key($purpose, $userId), 'limit' => $limit]);
        return max(0, (int) $stmt->fetchColumn());
    }

    private static function key(string $purpose, int $userId): string
    {
        return hash('sha256', $purpose . ':' . $userId);
    }

    public static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }
        db()->exec(
            'CREATE TABLE IF NOT EXISTS security_rate_limits (
                scope_key CHAR(64) PRIMARY KEY,
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                INDEX idx_security_rate_limits_expiry (expires_at)
            ) ENGINE=InnoDB'
        );
        self::$tableChecked = true;
    }
}
