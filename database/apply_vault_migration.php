<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$pdo = db();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS vault_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id INT UNSIGNED NULL,
        name VARCHAR(120) NOT NULL,
        slug VARCHAR(140) NOT NULL UNIQUE,
        description VARCHAR(255) NULL,
        icon VARCHAR(60) NOT NULL DEFAULT \'lock\',
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
    ) ENGINE=InnoDB'
);

$columns = $pdo->prepare(
    'SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND COLUMN_NAME = :column'
);
$columns->execute(['table' => 'vault_categories', 'column' => 'parent_id']);
if ((int) $columns->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE vault_categories ADD COLUMN parent_id INT UNSIGNED NULL AFTER id');
}

$indexes = $pdo->prepare(
    'SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND INDEX_NAME = :index_name'
);
$indexes->execute(['table' => 'vault_categories', 'index_name' => 'idx_vault_categories_parent']);
if ((int) $indexes->fetchColumn() === 0) {
    $pdo->exec('ALTER TABLE vault_categories ADD INDEX idx_vault_categories_parent (parent_id)');
}

$constraints = $pdo->prepare(
    'SELECT COUNT(*)
     FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table
       AND CONSTRAINT_NAME = :constraint_name'
);
$constraints->execute(['table' => 'vault_categories', 'constraint_name' => 'fk_vault_categories_parent']);
if ((int) $constraints->fetchColumn() === 0) {
    $pdo->exec(
        'ALTER TABLE vault_categories
         ADD CONSTRAINT fk_vault_categories_parent
         FOREIGN KEY (parent_id) REFERENCES vault_categories(id) ON DELETE SET NULL'
    );
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS vault_credentials (
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
    ) ENGINE=InnoDB'
);

$defaults = [
    ['Acessos administrativos', 'acessos-administrativos', 'Painéis, administradores e acessos privilegiados.', 'shield'],
    ['Rede e infraestrutura', 'rede-e-infraestrutura', 'Roteadores, firewalls, links, servidores e Wi-Fi.', 'router'],
    ['Cloud e hospedagem', 'cloud-e-hospedagem', 'Provedores cloud, hospedagens, DNS e domínios.', 'cloud'],
    ['Sistemas internos', 'sistemas-internos', 'ERPs, CRMs, portais e sistemas corporativos.', 'lock'],
    ['Bancos de dados', 'bancos-de-dados', 'Credenciais de bancos e conexões técnicas.', 'database'],
    ['E-mails e comunicação', 'emails-e-comunicacao', 'Contas de e-mail, comunicação e colaboração.', 'mail'],
    ['Financeiro', 'financeiro', 'Portais financeiros e acessos administrativos restritos.', 'building-2'],
    ['Outros', 'outros', 'Credenciais fora das categorias principais.', 'settings'],
];

$stmt = $pdo->prepare(
    'INSERT INTO vault_categories (name, slug, description, icon, is_active)
     VALUES (:name, :slug, :description, :icon, 1)
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        icon = VALUES(icon),
        is_active = 1'
);

foreach ($defaults as [$name, $slug, $description, $icon]) {
    $stmt->execute([
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'icon' => $icon,
    ]);
}

echo "Migracao do cofre aplicada.\n";
