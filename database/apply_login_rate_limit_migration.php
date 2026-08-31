<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

db()->exec(
    'CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(160) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_lookup (email, ip_address, attempted_at),
        INDEX idx_login_attempts_attempted_at (attempted_at)
    ) ENGINE=InnoDB'
);

echo "Login rate limit migration aplicada.\n";
