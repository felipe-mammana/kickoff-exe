<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function user_column_exists(string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column'
    );
    $stmt->execute(['table' => 'users', 'column' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

if (!user_column_exists('role')) {
    db()->exec("ALTER TABLE users ADD COLUMN role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer' AFTER password_hash");
    db()->exec("UPDATE users SET role = CASE WHEN is_admin = 1 THEN 'admin' ELSE 'viewer' END");
    echo "Coluna users.role criada.\n";
}

if (!user_column_exists('is_active')) {
    db()->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_admin');
    echo "Coluna users.is_active criada.\n";
}

echo "Migracao de gerenciamento de usuarios aplicada.\n";
