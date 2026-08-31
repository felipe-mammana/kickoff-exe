<?php

declare(strict_types=1);

class User
{
    public static function all(): array
    {
        return db()->query('SELECT id, name, email, is_admin, is_active, created_at FROM users ORDER BY is_active DESC, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, password_hash, is_admin, is_active)
             VALUES (:name, :email, :password_hash, :is_admin, :is_active)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_admin' => (int) $data['is_admin'],
            'is_active' => (int) $data['is_active'],
        ]);

        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET name = :name, email = :email, is_admin = :is_admin
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => (int) $data['is_admin'],
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
        $stmt = db()->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
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

    public static function setActiveSession(int $id, string $token): void
    {
        $stmt = db()->prepare(
            'UPDATE users
             SET active_session_token = :token, active_session_started_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'token' => $token,
        ]);
    }

    public static function clearActiveSession(int $id, ?string $token = null): void
    {
        $sql = 'UPDATE users
                SET active_session_token = NULL, active_session_started_at = NULL
                WHERE id = :id';
        $params = ['id' => $id];

        if ($token !== null) {
            $sql .= ' AND active_session_token = :token';
            $params['token'] = $token;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
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
            'secret' => CredentialCrypto::encrypt($secret),
        ]);
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

        return CredentialCrypto::decrypt($secret);
    }

    public static function duplicateEmailExists(string $email, ?int $ignoreId = null): bool
    {
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
}
