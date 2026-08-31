<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

db()->exec(
    'CREATE TABLE IF NOT EXISTS company_attachments (
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
    ) ENGINE=InnoDB'
);

$columns = db()->prepare(
    'SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND COLUMN_NAME = :column'
);
$columns->execute(['table' => 'company_attachments', 'column' => 'category_id']);
if ((int) $columns->fetchColumn() === 0) {
    db()->exec('ALTER TABLE company_attachments ADD COLUMN category_id INT UNSIGNED NULL AFTER company_id');
}

$indexes = db()->prepare(
    'SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND INDEX_NAME = :index_name'
);
$indexes->execute(['table' => 'company_attachments', 'index_name' => 'idx_company_attachments_category']);
if ((int) $indexes->fetchColumn() === 0) {
    db()->exec('ALTER TABLE company_attachments ADD INDEX idx_company_attachments_category (category_id)');
}

$constraints = db()->prepare(
    'SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND CONSTRAINT_NAME = :constraint_name'
);
$constraints->execute(['table' => 'company_attachments', 'constraint_name' => 'fk_company_attachments_category']);
if ((int) $constraints->fetchColumn() === 0) {
    db()->exec(
        'ALTER TABLE company_attachments
         ADD CONSTRAINT fk_company_attachments_category
         FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE SET NULL'
    );
}

echo "Tabela company_attachments pronta." . PHP_EOL;
