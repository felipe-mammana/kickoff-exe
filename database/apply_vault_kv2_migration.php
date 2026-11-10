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
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)
        || version_compare($matches[1] . '.' . $matches[2] . '.' . $matches[3], '8.0.16', '<')
    ) {
        throw new RuntimeException('MySQL 8.0.16 ou superior e necessario para CHECK constraints.');
    }

    $tableExists = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index_name');
        $stmt->execute(['table' => $table, 'index_name' => $index]);
        return (int) $stmt->fetchColumn() > 0;
    };
    $constraintExists = static function (string $table, string $constraint) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND CONSTRAINT_NAME = :constraint_name');
        $stmt->execute(['table' => $table, 'constraint_name' => $constraint]);
        return (int) $stmt->fetchColumn() > 0;
    };

    if (!$tableExists('vault_credentials')) {
        throw new RuntimeException('Tabela vault_credentials ausente. Aplique primeiro o schema base.');
    }

    $credentialCount = (int) $pdo->query('SELECT COUNT(*) FROM vault_credentials')->fetchColumn();
    $legacyColumns = [
        'secret_value', 'secret_backend', 'vault_path', 'shadow_vault_version',
        'migration_state', 'migration_attempts', 'migration_error_code', 'migration_updated_at',
    ];
    $hasLegacyColumns = false;
    foreach ($legacyColumns as $column) {
        $hasLegacyColumns = $hasLegacyColumns || $columnExists('vault_credentials', $column);
    }
    if ($credentialCount > 0 && $hasLegacyColumns) {
        throw new RuntimeException("Instalacao bloqueada: vault_credentials contem {$credentialCount} registro(s). Nenhum dado foi removido.");
    }

    if ($tableExists('vault_secret_operations') && $columnExists('vault_secret_operations', 'vault_path')) {
        $operationCount = (int) $pdo->query('SELECT COUNT(*) FROM vault_secret_operations')->fetchColumn();
        if ($operationCount > 0) {
            throw new RuntimeException('Instalacao bloqueada: o ledger legado contem operacoes.');
        }
        $pdo->exec('DROP TABLE vault_secret_operations');
        echo "Ledger legado vazio removido para recriacao.\n";
    }

    foreach ([
        'chk_vault_credentials_shadow_state', 'chk_vault_credentials_active_state',
        'chk_vault_credentials_backend_reference', 'chk_vault_credentials_shadow_version',
        'chk_vault_credentials_migration_state', 'chk_vault_credentials_backend',
    ] as $constraint) {
        if ($constraintExists('vault_credentials', $constraint)) {
            $pdo->exec("ALTER TABLE vault_credentials DROP CHECK `{$constraint}`");
        }
    }
    foreach (['uq_vault_credentials_vault_path', 'idx_vault_credentials_migration_state', 'idx_vault_credentials_backend'] as $index) {
        if ($indexExists('vault_credentials', $index)) {
            $pdo->exec("ALTER TABLE vault_credentials DROP INDEX `{$index}`");
        }
    }

    $columns = [
        'operational_state' => "VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending' AFTER username",
        'vault_version' => 'INT UNSIGNED NULL AFTER operational_state',
        'vault_operation_id' => 'CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER vault_version',
        'lock_version' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER vault_operation_id',
    ];
    foreach ($columns as $column => $definition) {
        if (!$columnExists('vault_credentials', $column)) {
            $pdo->exec("ALTER TABLE vault_credentials ADD COLUMN `{$column}` {$definition}");
            echo "Coluna vault_credentials.{$column} criada.\n";
        }
    }
    foreach ($legacyColumns as $column) {
        if ($columnExists('vault_credentials', $column)) {
            $pdo->exec("ALTER TABLE vault_credentials DROP COLUMN `{$column}`");
            echo "Coluna legada vault_credentials.{$column} removida da tabela vazia.\n";
        }
    }

    if (!$indexExists('vault_credentials', 'idx_vault_credentials_operational')) {
        $pdo->exec('ALTER TABLE vault_credentials ADD INDEX idx_vault_credentials_operational (operational_state, is_active)');
    }
    $credentialChecks = [
        'chk_vault_credentials_operational_state' => "operational_state IN ('pending', 'ready', 'error')",
        'chk_vault_credentials_vault_version' => 'vault_version IS NULL OR vault_version > 0',
        'chk_vault_credentials_operation_uuid' => "vault_operation_id IS NULL OR REGEXP_LIKE(vault_operation_id, '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$', 'c')",
        'chk_vault_credentials_reference_state' => "(operational_state = 'ready' AND vault_version IS NOT NULL AND vault_operation_id IS NOT NULL) OR (operational_state IN ('pending', 'error') AND vault_version IS NULL AND vault_operation_id IS NULL)",
    ];
    foreach ($credentialChecks as $name => $expression) {
        if (!$constraintExists('vault_credentials', $name)) {
            $pdo->exec("ALTER TABLE vault_credentials ADD CONSTRAINT `{$name}` CHECK ({$expression})");
        }
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS vault_secret_operations (
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
            UNIQUE INDEX uq_vault_secret_operations_version (credential_id, result_vault_version),
            INDEX idx_vault_secret_operations_credential (credential_id, created_at),
            INDEX idx_vault_secret_operations_reconcile (operation_state, next_attempt_at),
            CONSTRAINT fk_vault_secret_operations_credential FOREIGN KEY (credential_id) REFERENCES vault_credentials(id) ON DELETE RESTRICT,
            CONSTRAINT chk_vault_secret_operations_type CHECK (operation_type IN ('create', 'update')),
            CONSTRAINT chk_vault_secret_operations_state CHECK (operation_state IN ('pending', 'vault_confirmed', 'completed', 'failed')),
            CONSTRAINT chk_vault_secret_operations_uuid CHECK (REGEXP_LIKE(operation_id, '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$', 'c')),
            CONSTRAINT chk_vault_secret_operations_expected_version CHECK ((operation_type = 'create' AND expected_vault_version = 0 AND expected_lock_version = 0) OR (operation_type = 'update' AND expected_vault_version > 0)),
            CONSTRAINT chk_vault_secret_operations_result_version CHECK (result_vault_version IS NULL OR result_vault_version = expected_vault_version + 1),
            CONSTRAINT chk_vault_secret_operations_state_result CHECK ((operation_state IN ('pending', 'failed') AND result_vault_version IS NULL) OR (operation_state IN ('vault_confirmed', 'completed') AND result_vault_version IS NOT NULL)),
            CONSTRAINT chk_vault_secret_operations_completion CHECK ((operation_state = 'completed' AND completed_at IS NOT NULL) OR (operation_state <> 'completed' AND completed_at IS NULL)),
            CONSTRAINT chk_vault_secret_operations_failure CHECK (operation_state <> 'failed' OR error_code IS NOT NULL),
            CONSTRAINT chk_vault_secret_operations_error_code CHECK (error_code IS NULL OR REGEXP_LIKE(error_code, '^[A-Z0-9_]{1,64}$', 'c'))
        ) ENGINE=InnoDB"
    );

    echo "Estrutura limpa Vault KV v2 instalada. Nenhuma credencial foi migrada.\n";
})();
