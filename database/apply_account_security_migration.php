<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$columns = db()->query("SHOW COLUMNS FROM users")->fetchAll();
$existing = array_column($columns, 'Field');

$definitions = [
    'two_factor_enabled' => 'ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
    'two_factor_secret' => 'ALTER TABLE users ADD COLUMN two_factor_secret TEXT NULL AFTER two_factor_enabled',
    'active_session_token' => 'ALTER TABLE users ADD COLUMN active_session_token VARCHAR(128) NULL AFTER two_factor_secret',
    'active_session_started_at' => 'ALTER TABLE users ADD COLUMN active_session_started_at TIMESTAMP NULL AFTER active_session_token',
];

foreach ($definitions as $column => $sql) {
    if (in_array($column, $existing, true)) {
        echo "Coluna users.{$column} ja existe.\n";
        continue;
    }

    db()->exec($sql);
    echo "Coluna users.{$column} criada.\n";
}

echo "Migração de segurança da conta concluída.\n";
