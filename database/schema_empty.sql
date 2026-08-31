
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    two_factor_secret TEXT NULL,
    active_session_token VARCHAR(128) NULL,
    active_session_started_at TIMESTAMP NULL,
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

CREATE TABLE IF NOT EXISTS vault_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
    INDEX idx_vault_categories_parent (parent_id),
    INDEX idx_vault_categories_active (is_active),
    CONSTRAINT fk_vault_categories_parent FOREIGN KEY (parent_id) REFERENCES vault_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_categories_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

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
    secret_value TEXT NOT NULL,
    notes TEXT NULL,
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
    CONSTRAINT fk_vault_credentials_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_vault_credentials_category FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_credentials_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_vault_credentials_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
