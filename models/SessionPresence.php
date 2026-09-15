<?php
declare(strict_types=1);

class SessionPresence
{
    public const ONLINE_SECONDS = 120;
    private static bool $ready = false;

    public static function ensureTable(): void
    {
        if (self::$ready) return;
        db()->exec('CREATE TABLE IF NOT EXISTS session_presence (
            user_id INT UNSIGNED PRIMARY KEY,
            session_hash CHAR(64) NOT NULL,
            last_seen_at DATETIME NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB');
        self::$ready = true;
    }

    public static function touch(int $userId, string $token): void
    {
        self::ensureTable();
        $stmt = db()->prepare('INSERT INTO session_presence (user_id, session_hash, last_seen_at, ip_address, user_agent)
            VALUES (:id, :hash, NOW(), :ip, :agent)
            ON DUPLICATE KEY UPDATE session_hash = VALUES(session_hash), last_seen_at = NOW(),
            ip_address = VALUES(ip_address), user_agent = VALUES(user_agent)');
        $stmt->execute(['id' => $userId, 'hash' => hash('sha256', $token),
            'ip' => substr(client_ip(), 0, 45), 'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    }

    public static function all(): array
    {
        self::ensureTable();
        return db()->query('SELECT u.id, u.name, u.email, u.is_active, (u.active_session_token IS NOT NULL) AS has_session,
            p.last_seen_at, p.ip_address, p.user_agent,
            CASE WHEN u.is_active = 1 AND u.active_session_token IS NOT NULL
                AND p.session_hash = SHA2(u.active_session_token, 256)
                AND p.last_seen_at >= DATE_SUB(NOW(), INTERVAL 120 SECOND)
                AND (u.session_timeout_minutes = 0 OR TIMESTAMPDIFF(SECOND, u.active_session_started_at, NOW()) < u.session_timeout_minutes * 60)
                THEN 1 ELSE 0 END AS online,
            a.description AS last_action, a.created_at AS last_action_at
            FROM users u LEFT JOIN session_presence p ON p.user_id = u.id
            LEFT JOIN audit_logs a ON a.id = (SELECT MAX(al.id) FROM audit_logs al WHERE al.user_id = u.id)
            ORDER BY online DESC, p.last_seen_at DESC, u.name')->fetchAll();
    }

    public static function browser(string $agent): string
    {
        foreach (['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Version' => 'Safari'] as $marker => $name) {
            if (preg_match('#' . $marker . '/([0-9]+)#', $agent, $match)) return $name . ' ' . $match[1];
        }
        return $agent === '' ? 'Nao informado' : 'Outro navegador';
    }
}
