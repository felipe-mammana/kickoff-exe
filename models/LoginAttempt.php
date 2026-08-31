<?php

declare(strict_types=1);

class LoginAttempt
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 600;

    private static bool $tableChecked = false;

    public static function isBlocked(string $email, string $ipAddress): bool
    {
        self::ensureTable();
        self::deleteExpired();

        return self::countRecentFailures($email, $ipAddress) >= self::MAX_ATTEMPTS;
    }

    public static function recordFailure(string $email, string $ipAddress): void
    {
        self::ensureTable();

        $stmt = db()->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempted_at)
             VALUES (:email, :ip_address, NOW())'
        );
        $stmt->execute([
            'email' => self::normalizeEmail($email),
            'ip_address' => self::normalizeIp($ipAddress),
        ]);
    }

    public static function clear(string $email, string $ipAddress): void
    {
        self::ensureTable();

        $stmt = db()->prepare(
            'DELETE FROM login_attempts
             WHERE email = :email AND ip_address = :ip_address'
        );
        $stmt->execute([
            'email' => self::normalizeEmail($email),
            'ip_address' => self::normalizeIp($ipAddress),
        ]);
    }

    public static function remainingSeconds(string $email, string $ipAddress): int
    {
        self::ensureTable();

        $firstAttempt = self::firstRecentAttemptAt($email, $ipAddress);
        if ($firstAttempt === null) {
            return 0;
        }

        return max(0, strtotime($firstAttempt) + self::WINDOW_SECONDS - time());
    }

    private static function countRecentFailures(string $email, string $ipAddress): int
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM login_attempts
             WHERE email = :email
               AND ip_address = :ip_address
               AND attempted_at >= (NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)'
        );
        $stmt->bindValue('email', self::normalizeEmail($email));
        $stmt->bindValue('ip_address', self::normalizeIp($ipAddress));
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private static function firstRecentAttemptAt(string $email, string $ipAddress): ?string
    {
        $stmt = db()->prepare(
            'SELECT attempted_at
             FROM login_attempts
             WHERE email = :email
               AND ip_address = :ip_address
               AND attempted_at >= (NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)
             ORDER BY attempted_at ASC
             LIMIT 1'
        );
        $stmt->bindValue('email', self::normalizeEmail($email));
        $stmt->bindValue('ip_address', self::normalizeIp($ipAddress));
        $stmt->execute();
        $attemptedAt = $stmt->fetchColumn();

        return $attemptedAt === false ? null : (string) $attemptedAt;
    }

    private static function deleteExpired(): void
    {
        $stmt = db()->prepare(
            'DELETE FROM login_attempts
             WHERE attempted_at < (NOW() - INTERVAL ' . self::WINDOW_SECONDS . ' SECOND)'
        );
        $stmt->execute();
    }

    private static function ensureTable(): void
    {
        if (self::$tableChecked) {
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS login_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(160) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_attempts_lookup (email, ip_address, attempted_at),
                INDEX idx_login_attempts_attempted_at (attempted_at)
            ) ENGINE=InnoDB'
        );

        self::$tableChecked = true;
    }

    private static function normalizeEmail(string $email): string
    {
        return substr(strtolower(trim($email)), 0, 160);
    }

    private static function normalizeIp(string $ipAddress): string
    {
        return substr(trim($ipAddress), 0, 45);
    }
}
