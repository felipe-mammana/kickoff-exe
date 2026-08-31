<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$columns = [
    'preferred_theme' => "ALTER TABLE users ADD COLUMN preferred_theme VARCHAR(16) NOT NULL DEFAULT 'light' AFTER active_session_started_at",
    'sidebar_default' => "ALTER TABLE users ADD COLUMN sidebar_default VARCHAR(16) NOT NULL DEFAULT 'expanded' AFTER preferred_theme",
    'table_page_size' => 'ALTER TABLE users ADD COLUMN table_page_size INT UNSIGNED NOT NULL DEFAULT 25 AFTER sidebar_default',
    'datetime_format' => "ALTER TABLE users ADD COLUMN datetime_format VARCHAR(24) NOT NULL DEFAULT 'd/m/Y H:i' AFTER table_page_size",
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

echo "Migracao de preferencias concluida.\n";
