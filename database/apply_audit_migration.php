<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

function column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function index_exists(string $table, string $index): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
    );
    $stmt->execute([
        'table_name' => $table,
        'index_name' => $index,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function add_column_if_missing(string $table, string $column, string $definition): void
{
    if (!column_exists($table, $column)) {
        db()->exec("ALTER TABLE {$table} ADD COLUMN {$definition}");
        echo "Coluna {$table}.{$column} criada." . PHP_EOL;
    }
}

if (!column_exists('users', 'is_admin')) {
    db()->exec('ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash');
    echo "Coluna users.is_admin criada." . PHP_EOL;
}

add_column_if_missing('companies', 'tag_pattern', 'tag_pattern VARCHAR(160) NULL AFTER name');
add_column_if_missing('companies', 'is_active', 'is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER tag_pattern');
add_column_if_missing('companies', 'created_by', 'created_by INT UNSIGNED NULL AFTER is_active');
add_column_if_missing('companies', 'updated_by', 'updated_by INT UNSIGNED NULL AFTER created_by');
add_column_if_missing('companies', 'updated_at', 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by');

add_column_if_missing('machines', 'device_type', "device_type VARCHAR(40) NOT NULL DEFAULT 'notebook' AFTER company_id");
add_column_if_missing('machines', 'equipment_name', 'equipment_name VARCHAR(160) NULL AFTER device_type');
add_column_if_missing('machines', 'brand', 'brand VARCHAR(160) NULL AFTER department');
add_column_if_missing('machines', 'operating_system', 'operating_system VARCHAR(160) NULL AFTER computer_model');
add_column_if_missing('machines', 'admin_user', 'admin_user VARCHAR(160) NULL AFTER machine_password');
add_column_if_missing('machines', 'admin_password', 'admin_password TEXT NULL AFTER admin_user');
add_column_if_missing('machines', 'install_location', 'install_location VARCHAR(160) NULL AFTER admin_password');
add_column_if_missing('machines', 'modem_name', 'modem_name VARCHAR(160) NULL AFTER install_location');
add_column_if_missing('machines', 'ip_address', 'ip_address VARCHAR(80) NULL AFTER modem_name');
add_column_if_missing('machines', 'gateway', 'gateway VARCHAR(80) NULL AFTER ip_address');
add_column_if_missing('machines', 'carrier', 'carrier VARCHAR(160) NULL AFTER gateway');
add_column_if_missing('machines', 'printer_brand', 'printer_brand VARCHAR(160) NULL AFTER modem_name');
add_column_if_missing('machines', 'printer_connection_type', 'printer_connection_type VARCHAR(40) NULL AFTER printer_brand');
add_column_if_missing('machines', 'printer_shared', 'printer_shared TINYINT(1) NOT NULL DEFAULT 0 AFTER printer_connection_type');
add_column_if_missing('machines', 'is_active', 'is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER requester_in_tflux');
add_column_if_missing('machines', 'updated_by', 'updated_by INT UNSIGNED NULL AFTER created_by');
add_column_if_missing('machine_photos', 'photo_type', "photo_type VARCHAR(40) NOT NULL DEFAULT 'general' AFTER machine_id");

if (index_exists('machines', 'unique_company_new_hostname')) {
    db()->exec('ALTER TABLE machines DROP INDEX unique_company_new_hostname');
    echo "Indice unique_company_new_hostname removido para suportar dispositivos sem hostname." . PHP_EOL;
}

db()->exec('ALTER TABLE machines MODIFY tag VARCHAR(80) NULL');
db()->exec('ALTER TABLE machines MODIFY old_hostname VARCHAR(120) NULL');
db()->exec('ALTER TABLE machines MODIFY new_hostname VARCHAR(120) NULL');
db()->exec('ALTER TABLE machines MODIFY employee_name VARCHAR(160) NULL');
db()->exec('ALTER TABLE machines MODIFY department VARCHAR(120) NULL');
db()->exec('ALTER TABLE machines MODIFY computer_model VARCHAR(160) NULL');

db()->exec(
    'CREATE TABLE IF NOT EXISTS audit_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        user_name VARCHAR(120) NULL,
        user_email VARCHAR(160) NULL,
        action_type VARCHAR(80) NOT NULL,
        affected_table VARCHAR(80) NULL,
        affected_record_id INT UNSIGNED NULL,
        company_id INT UNSIGNED NULL,
        machine_id INT UNSIGNED NULL,
        description VARCHAR(255) NOT NULL,
        old_data LONGTEXT NULL,
        new_data LONGTEXT NULL,
        ip_address VARCHAR(45) NULL,
        session_identifier VARCHAR(128) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_user (user_id),
        INDEX idx_audit_action (action_type),
        INDEX idx_audit_company (company_id),
        INDEX idx_audit_machine (machine_id),
        INDEX idx_audit_created_at (created_at),
        CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        CONSTRAINT fk_audit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
        CONSTRAINT fk_audit_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL
    ) ENGINE=InnoDB'
);

db()->exec(
    'CREATE TABLE IF NOT EXISTS api_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        last_used_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        revoked_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_api_tokens_user (user_id),
        INDEX idx_api_tokens_expires_at (expires_at),
        CONSTRAINT fk_api_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB'
);

db()->exec(
    "UPDATE users
     SET is_admin = 1
     WHERE email = " . db()->quote((string) config_value('ADMIN_EMAIL', 'admin@empresa.com'))
);

echo "Auditoria pronta." . PHP_EOL;
