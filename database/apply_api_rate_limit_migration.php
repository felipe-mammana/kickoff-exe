<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

db()->exec(
    'CREATE TABLE IF NOT EXISTS api_rate_limits (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        scope_key CHAR(64) NOT NULL UNIQUE,
        scope_name VARCHAR(190) NOT NULL,
        request_count INT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_request_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_rate_limits_last_request (last_request_at)
    ) ENGINE=InnoDB'
);

echo "API rate limit migration aplicada.\n";
