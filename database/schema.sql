CREATE DATABASE IF NOT EXISTS inventario_ti
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventario_ti;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret TEXT NULL,
    active_session_token VARCHAR(128) NULL,
    active_session_started_at TIMESTAMP NULL,
    active_session_ip VARCHAR(45) NULL,
    active_session_user_agent VARCHAR(255) NULL,
    preferred_theme VARCHAR(16) NOT NULL DEFAULT 'light',
    sidebar_default VARCHAR(16) NOT NULL DEFAULT 'expanded',
    table_page_size INT UNSIGNED NOT NULL DEFAULT 25,
    datetime_format VARCHAR(24) NOT NULL DEFAULT 'd/m/Y H:i',
    session_timeout_minutes INT UNSIGNED NOT NULL DEFAULT 480,
    vault_require_password_reveal TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL UNIQUE,
    tag_pattern VARCHAR(160) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    device_type VARCHAR(40) NOT NULL DEFAULT 'notebook',
    equipment_name VARCHAR(160) NULL,
    tag VARCHAR(80) NULL,
    old_hostname VARCHAR(120) NULL,
    new_hostname VARCHAR(120) NULL,
    employee_name VARCHAR(160) NULL,
    department VARCHAR(120) NULL,
    brand VARCHAR(160) NULL,
    computer_model VARCHAR(160) NULL,
    operating_system VARCHAR(160) NULL,
    machine_password TEXT NULL,
    admin_user VARCHAR(160) NULL,
    admin_password TEXT NULL,
    install_location VARCHAR(160) NULL,
    modem_name VARCHAR(160) NULL,
    ip_address VARCHAR(80) NULL,
    gateway VARCHAR(80) NULL,
    carrier VARCHAR(160) NULL,
    printer_brand VARCHAR(160) NULL,
    printer_connection_type VARCHAR(40) NULL,
    printer_shared TINYINT(1) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    tflux_installed TINYINT(1) NOT NULL DEFAULT 0,
    antivirus_installed TINYINT(1) NOT NULL DEFAULT 0,
    requester_in_tflux TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_machines_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_machines_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_company_tag (company_id, tag),
    UNIQUE KEY unique_company_new_hostname (company_id, new_hostname)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
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
    user_agent VARCHAR(255) NULL,
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS machine_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    machine_id INT UNSIGNED NOT NULL,
    photo_type VARCHAR(40) NOT NULL DEFAULT 'general',
    photo_topic VARCHAR(40) NOT NULL DEFAULT 'equipamento',
    location_name VARCHAR(160) NULL,
    file_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(80) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_machine_photos_machine FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_tokens (
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
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_rate_limits (
    scope_key CHAR(64) PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX idx_security_rate_limits_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(160) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_lookup (email, ip_address, attempted_at),
    INDEX idx_login_attempts_attempted_at (attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_key CHAR(64) NOT NULL UNIQUE,
    scope_name VARCHAR(190) NOT NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_request_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_rate_limits_last_request (last_request_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vault_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NULL,
    parent_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    icon VARCHAR(60) NOT NULL DEFAULT 'lock',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vault_categories_company (company_id),
    INDEX idx_vault_categories_parent (parent_id),
    INDEX idx_vault_categories_active (is_active),
    CONSTRAINT fk_vault_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_categories_parent FOREIGN KEY (parent_id) REFERENCES vault_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS company_vault_categories (
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (company_id, category_id),
    INDEX idx_company_vault_categories_category (category_id, company_id),
    CONSTRAINT fk_company_vault_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_vault_categories_category FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='company_vault_categories:v1:complete';

CREATE TABLE IF NOT EXISTS company_attachments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    disk_name VARCHAR(255) NOT NULL UNIQUE,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    description VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_attachments_company (company_id),
    INDEX idx_company_attachments_category (category_id),
    INDEX idx_company_attachments_created_at (created_at),
    CONSTRAINT fk_company_attachments_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_attachments_category FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_company_attachments_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vault_credentials (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    title VARCHAR(160) NOT NULL,
    service_url VARCHAR(255) NULL,
    username VARCHAR(190) NULL,
    operational_state VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
    vault_version INT UNSIGNED NULL,
    vault_operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    lock_version BIGINT UNSIGNED NOT NULL DEFAULT 0,
    notes TEXT NULL,
    custom_fields TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_revealed_at TIMESTAMP NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vault_credentials_company (company_id),
    INDEX idx_vault_credentials_category (category_id),
    INDEX idx_vault_credentials_active (is_active),
    INDEX idx_vault_credentials_updated_at (updated_at),
    INDEX idx_vault_credentials_operational (operational_state, is_active),
    CONSTRAINT fk_vault_credentials_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_credentials_category FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_credentials_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_credentials_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_vault_credentials_operational_state CHECK (operational_state IN ('pending', 'ready', 'error')),
    CONSTRAINT chk_vault_credentials_vault_version CHECK (vault_version IS NULL OR vault_version > 0),
    CONSTRAINT chk_vault_credentials_operation_uuid CHECK (
        vault_operation_id IS NULL OR REGEXP_LIKE(vault_operation_id, '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$', 'c')
    ),
    CONSTRAINT chk_vault_credentials_reference_state CHECK (
        (operational_state = 'ready' AND vault_version IS NOT NULL AND vault_operation_id IS NOT NULL)
        OR (operational_state IN ('pending', 'error') AND vault_version IS NULL AND vault_operation_id IS NULL)
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vault_secret_operations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    operation_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    credential_id BIGINT UNSIGNED NOT NULL,
    operation_type VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    operation_state VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
    expected_vault_version INT UNSIGNED NOT NULL,
    result_vault_version INT UNSIGNED NULL,
    expected_lock_version BIGINT UNSIGNED NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    error_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    next_attempt_at TIMESTAMP NULL DEFAULT NULL,
    last_attempt_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE INDEX uq_vault_secret_operations_id (operation_id),
    INDEX idx_vault_secret_operations_credential (credential_id, created_at),
    INDEX idx_vault_secret_operations_reconcile (operation_state, next_attempt_at),
    UNIQUE INDEX uq_vault_secret_operations_version (credential_id, result_vault_version),
    CONSTRAINT fk_vault_secret_operations_credential FOREIGN KEY (credential_id) REFERENCES vault_credentials(id) ON DELETE RESTRICT,
    CONSTRAINT chk_vault_secret_operations_type CHECK (operation_type IN ('create', 'update')),
    CONSTRAINT chk_vault_secret_operations_state CHECK (operation_state IN ('pending', 'vault_confirmed', 'completed', 'failed')),
    CONSTRAINT chk_vault_secret_operations_uuid CHECK (
        REGEXP_LIKE(operation_id, '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$', 'c')
    ),
    CONSTRAINT chk_vault_secret_operations_expected_version CHECK (
        (operation_type = 'create' AND expected_vault_version = 0 AND expected_lock_version = 0)
        OR (operation_type = 'update' AND expected_vault_version > 0)
    ),
    CONSTRAINT chk_vault_secret_operations_result_version CHECK (
        result_vault_version IS NULL OR result_vault_version = expected_vault_version + 1
    ),
    CONSTRAINT chk_vault_secret_operations_state_result CHECK (
        (operation_state IN ('pending', 'failed') AND result_vault_version IS NULL)
        OR (operation_state IN ('vault_confirmed', 'completed') AND result_vault_version IS NOT NULL)
    ),
    CONSTRAINT chk_vault_secret_operations_completion CHECK (
        (operation_state = 'completed' AND completed_at IS NOT NULL)
        OR (operation_state <> 'completed' AND completed_at IS NULL)
    ),
    CONSTRAINT chk_vault_secret_operations_failure CHECK (
        operation_state <> 'failed' OR error_code IS NOT NULL
    ),
    CONSTRAINT chk_vault_secret_operations_error_code CHECK (
        error_code IS NULL OR REGEXP_LIKE(error_code, '^[A-Z0-9_]{1,64}$', 'c')
    )
) ENGINE=InnoDB;

INSERT INTO companies (name)
SELECT 'Empresa Matriz'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Empresa Matriz');

INSERT INTO companies (name)
SELECT 'Filial Operacional'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Filial Operacional');
