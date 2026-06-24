<?php

declare(strict_types=1);

class User
{
    public static function all(): array
    {
        return db()->query('SELECT id, name, email, is_admin FROM users ORDER BY name')->fetchAll();
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        return $user ?: null;
    }
}
