<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$stmt = db()->prepare(
    'SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND COLUMN_NAME = :column'
);
$stmt->execute(['table' => 'users', 'column' => 'is_active']);

if ((int) $stmt->fetchColumn() === 0) {
    db()->exec('ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_admin');
    echo "Coluna users.is_active criada.\n";
}

echo "Migracao de gerenciamento de usuarios aplicada.\n";
