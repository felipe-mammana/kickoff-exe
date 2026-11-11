<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

(static function (): void {
    $pdo = db();
    VaultKv2DatabaseTargetGuard::assertMigrationTarget($pdo);
    $tableCheck = $pdo->prepare(
        'SELECT TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $tableCheck->execute(['table_name' => 'company_vault_categories']);
    $existingTableComment = $tableCheck->fetchColumn();
    $tableAlreadyExisted = $existingTableComment !== false;
    $pendingMarker = 'company_vault_categories:v1:pending';
    $completeMarker = 'company_vault_categories:v1:complete';
    $initialLoadRequired = !$tableAlreadyExisted || $existingTableComment === $pendingMarker;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS company_vault_categories (
            company_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (company_id, category_id),
            INDEX idx_company_vault_categories_category (category_id, company_id),
            CONSTRAINT fk_company_vault_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            CONSTRAINT fk_company_vault_categories_category FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB COMMENT=' . $pdo->quote($pendingMarker)
    );

    if ($initialLoadRequired) {
        $pdo->exec(
            'INSERT IGNORE INTO company_vault_categories (company_id, category_id)
             SELECT company.id, category.id
             FROM companies company
             CROSS JOIN vault_categories category
             WHERE category.company_id IS NULL
               AND category.parent_id IS NULL
               AND category.is_active = 1'
        );
        $pdo->exec('ALTER TABLE company_vault_categories COMMENT = ' . $pdo->quote($completeMarker));
    }

    echo $initialLoadRequired
        ? "Categorias globais atuais vinculadas às empresas existentes. Nenhuma categoria ou credencial foi alterada.\n"
        : "Estrutura de categorias por empresa já estava instalada; vínculos existentes foram preservados.\n";
})();
