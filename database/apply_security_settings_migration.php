<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$columns = [
    'active_session_ip' => 'ALTER TABLE users ADD COLUMN active_session_ip VARCHAR(45) NULL AFTER active_session_started_at',
    'active_session_user_agent' => 'ALTER TABLE users ADD COLUMN active_session_user_agent VARCHAR(255) NULL AFTER active_session_ip',
    'session_timeout_minutes' => 'ALTER TABLE users ADD COLUMN session_timeout_minutes INT UNSIGNED NOT NULL DEFAULT 480 AFTER datetime_format',
    'vault_require_password_reveal' => 'ALTER TABLE users ADD COLUMN vault_require_password_reveal TINYINT(1) NOT NULL DEFAULT 0 AFTER session_timeout_minutes',
];

$existing = db()->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);

foreach ($columns as $column => $sql) {
    if (in_array($column, $existing, true)) {
        echo "{$column}: ja existe\n";
        continue;
    }

    db()->exec($sql);
    echo "{$column}: criado\n";
}

echo "Migracao de seguranca concluida.\n";
