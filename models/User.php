<?php

declare(strict_types=1);

class User
{
    public const ROLES = [
        'admin' => 'Administrador',
        'editor' => 'Editor',
        'viewer' => 'Usuário',
    ];

    public static function all(): array
    {
        self::ensureRoleColumn();

        return db()->query('SELECT id, name, email, role, is_admin, is_active, created_at FROM users ORDER BY is_active DESC, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        self::ensureRoleColumn();

        $stmt = db()->prepare(
            'SELECT users.*, TIMESTAMPDIFF(SECOND, active_session_started_at, NOW()) AS active_session_age_seconds
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        self::ensureRoleColumn();

        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(array $data): int
    {
        if (!PasswordSecurity::valid((string) $data['password'])) {
            throw new InvalidArgumentException(PasswordSecurity::REQUIREMENTS);
        }
        self::ensureRoleColumn();

        $role = self::normalizeRole((string) ($data['role'] ?? (!empty($data['is_admin']) ? 'admin' : 'viewer')));
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password_hash, role, is_admin, is_active)
             VALUES (:name, :email, :password_hash, :role, :is_admin, :is_active)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => PasswordSecurity::hash($data['password']),
            'role' => $role,
            'is_admin' => $role === 'admin' ? 1 : 0,
            'is_active' => (int) $data['is_active'],
        ]);

        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        self::ensureRoleColumn();

        $role = self::normalizeRole((string) ($data['role'] ?? (!empty($data['is_admin']) ? 'admin' : 'viewer')));
        $stmt = db()->prepare(
            'UPDATE users
             SET name = :name, email = :email, role = :role, is_admin = :is_admin
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $role,
            'is_admin' => $role === 'admin' ? 1 : 0,
        ]);
    }

    public static function updateProfile(int $id, string $name, string $email): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET name = :name, email = :email
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'email' => $email,
        ]);
    }

    public static function updatePassword(int $id, string $password): void
    {
        if (!PasswordSecurity::valid($password)) {
            throw new InvalidArgumentException(PasswordSecurity::REQUIREMENTS);
        }
        $passwordHash = PasswordSecurity::hash($password);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET password_hash = :password_hash,
                 active_session_token = NULL, active_session_started_at = NULL,
                 active_session_ip = NULL, active_session_user_agent = NULL WHERE id = :id'
            );
            $stmt->execute(['id' => $id, 'password_hash' => $passwordHash]);
            ApiToken::revokeAllForUser($id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public static function authenticationFingerprint(array $user): string
    {
        return hash('sha256', json_encode([
            $user['id'], $user['password_hash'], $user['email'],
            $user['two_factor_enabled'] ?? 0, $user['two_factor_secret'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    public static function updatePreferences(int $id, array $preferences): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET preferred_theme = :preferred_theme,
                 sidebar_default = :sidebar_default,
                 table_page_size = :table_page_size,
                 datetime_format = :datetime_format
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'preferred_theme' => $preferences['preferred_theme'],
            'sidebar_default' => $preferences['sidebar_default'],
            'table_page_size' => (int) $preferences['table_page_size'],
            'datetime_format' => $preferences['datetime_format'],
        ]);
    }

    public static function setActive(int $id, bool $active): void
    {
        $stmt = db()->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);
    }

    public static function setActiveSession(int $id, string $token, ?array $authenticatedUser = null): bool
    {
        $sql = 'UPDATE users
             SET active_session_token = :token,
                 active_session_started_at = CURRENT_TIMESTAMP,
                 active_session_ip = :ip_address,
                 active_session_user_agent = :user_agent
             WHERE id = :id';
        $params = [
            'id' => $id,
            'token' => $token,
            'ip_address' => client_ip(),
            'user_agent' => self::limitString($_SERVER['HTTP_USER_AGENT'] ?? null, 255),
        ];
        if ($authenticatedUser !== null) {
            // A password reset racing with login must not create a new session with old credentials.
            $sql .= ' AND is_active = 1 AND password_hash = :expected_password
                      AND email = :expected_email AND two_factor_enabled = :expected_2fa
                      AND two_factor_secret <=> :expected_secret';
            $params += [
                'expected_password' => $authenticatedUser['password_hash'],
                'expected_email' => $authenticatedUser['email'],
                'expected_2fa' => (int) ($authenticatedUser['two_factor_enabled'] ?? 0),
                'expected_secret' => $authenticatedUser['two_factor_secret'] ?? null,
            ];
        }
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function clearActiveSession(int $id, ?string $token = null): void
    {
        $sql = 'UPDATE users
             SET active_session_token = NULL, active_session_started_at = NULL
             , active_session_ip = NULL, active_session_user_agent = NULL
                WHERE id = :id';
        $params = ['id' => $id];

        if ($token !== null) {
            $sql .= ' AND active_session_token = :token';
            $params['token'] = $token;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
    }

    public static function updateSecurityPreferences(int $id, int $sessionTimeoutMinutes, bool $vaultRequirePasswordReveal): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET session_timeout_minutes = :session_timeout_minutes,
                 vault_require_password_reveal = :vault_require_password_reveal
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'session_timeout_minutes' => $sessionTimeoutMinutes,
            'vault_require_password_reveal' => $vaultRequirePasswordReveal ? 1 : 0,
        ]);
    }

    public static function enableTwoFactor(int $id, string $secret): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET two_factor_enabled = 1, two_factor_secret = :secret
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'secret' => CredentialCrypto::encrypt($secret, 'totp'),
        ]);
    }

    public static function enableEmailTwoFactor(int $id): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET two_factor_enabled = 1, two_factor_secret = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function disableTwoFactor(int $id): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET two_factor_enabled = 0, two_factor_secret = NULL
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public static function twoFactorSecret(array $user): ?string
    {
        $secret = $user['two_factor_secret'] ?? null;
        if (!is_string($secret) || $secret === '') {
            return null;
        }

        return CredentialCrypto::decrypt($secret, 'totp');
    }

    public static function duplicateEmailExists(string $email, ?int $ignoreId = null): bool
    {
        self::ensureRoleColumn();

        $sql = 'SELECT id FROM users WHERE email = :email';
        $params = ['email' => strtolower(trim($email))];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public static function activeAdminCount(?int $ignoreId = null): int
    {
        self::ensureRoleColumn();

        $sql = 'SELECT COUNT(*) FROM users WHERE is_admin = 1 AND is_active = 1';
        $params = [];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function normalizeRole(string $role): string
    {
        return array_key_exists($role, self::ROLES) ? $role : 'viewer';
    }

    public static function roleFromUser(array $user): string
    {
        if (!empty($user['role'])) {
            return self::normalizeRole((string) $user['role']);
        }

        return !empty($user['is_admin']) ? 'admin' : 'viewer';
    }

    public static function roleLabel(string $role): string
    {
        return self::ROLES[self::normalizeRole($role)];
    }

    public static function ensureRoleColumn(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $stmt = db()->query("SHOW COLUMNS FROM users LIKE 'role'");
        if (!$stmt->fetch()) {
            db()->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer' AFTER password_hash");
            db()->exec("UPDATE users SET role = CASE WHEN is_admin = 1 THEN 'admin' ELSE 'viewer' END");
        }

        $ensured = true;
    }

    private static function limitString($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }
}
