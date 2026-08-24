CREATE DATABASE IF NOT EXISTS inventario_ti
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE inventario_ti;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
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

INSERT INTO companies (name)
SELECT 'Empresa Matriz'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Empresa Matriz');

INSERT INTO companies (name)
SELECT 'Filial Operacional'
WHERE NOT EXISTS (SELECT 1 FROM companies WHERE name = 'Filial Operacional');
