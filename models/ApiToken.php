<?php

declare(strict_types=1);

class ApiToken
{
    public static function create(int $userId, string $name, string $plainToken, ?string $expiresAt = null): int
    {
        $stmt = db()->prepare(
            'INSERT INTO api_tokens (user_id, name, token_hash, expires_at)
             VALUES (:user_id, :name, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'token_hash' => self::hash($plainToken),
            'expires_at' => $expiresAt,
        ]);

        return (int) db()->lastInsertId();
    }

    public static function findActiveByPlainToken(string $plainToken): ?array
    {
        try {
            $stmt = db()->prepare(
                'SELECT t.*, u.name AS user_name, u.email AS user_email, u.is_admin
                 FROM api_tokens t
                 INNER JOIN users u ON u.id = t.user_id
                 WHERE t.token_hash = :token_hash
                   AND u.is_active = 1
                   AND t.revoked_at IS NULL
                   AND (t.expires_at IS NULL OR t.expires_at > NOW())
                 LIMIT 1'
            );
            $stmt->execute(['token_hash' => self::hash($plainToken)]);
            $token = $stmt->fetch();
        } catch (PDOException $exception) {
            error_log('API token lookup failed: ' . $exception->getMessage());

            return null;
        }

        return $token ?: null;
    }

    public static function markUsed(int $id): void
    {
        $stmt = db()->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function generatePlainToken(): string
    {
        return 'exe_' . bin2hex(random_bytes(32));
    }

    private static function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
