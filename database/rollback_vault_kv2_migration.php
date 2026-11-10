<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

(static function (): void {
    $isDisposableTest = getenv('VAULT_KV2_DISPOSABLE_TEST') === '1';
    if ($isDisposableTest) {
        if (APP_ENV !== 'testing'
            || !in_array(strtolower(DB_HOST), ['127.0.0.1', 'localhost', '::1'], true)
            || !preg_match('/^kickoff_vault_kv2_test_[0-9a-f]{16}$/', DB_NAME)
        ) {
            throw new RuntimeException('Rollback recusado: alvo descartavel invalido.');
        }
    } elseif (APP_ENV !== 'homologacao' || DB_NAME !== 'kickoff_hml') {
        throw new RuntimeException('Rollback recusado: use APP_ENV=homologacao e o banco kickoff_hml.');
    }

    $pdo = db();
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
        echo "Tabela vault_credentials ausente; rollback nao necessario.\n";
        return;
    }

    $credentialCount = (int) $pdo->query('SELECT COUNT(*) FROM vault_credentials')->fetchColumn();
    if ($credentialCount > 0) {
        throw new RuntimeException("Rollback bloqueado: vault_credentials contem {$credentialCount} registro(s).");
    }
    if ($tableExists('vault_secret_operations')) {
        $operationCount = (int) $pdo->query('SELECT COUNT(*) FROM vault_secret_operations')->fetchColumn();
        if ($operationCount > 0) {
            throw new RuntimeException("Rollback bloqueado: vault_secret_operations contem {$operationCount} operacao(oes).");
        }
        $pdo->exec('DROP TABLE vault_secret_operations');
    }

    foreach ([
        'chk_vault_credentials_reference_state', 'chk_vault_credentials_operation_uuid',
        'chk_vault_credentials_vault_version', 'chk_vault_credentials_operational_state',
    ] as $constraint) {
        if ($constraintExists('vault_credentials', $constraint)) {
            $pdo->exec("ALTER TABLE vault_credentials DROP CHECK `{$constraint}`");
        }
    }
    if ($indexExists('vault_credentials', 'idx_vault_credentials_operational')) {
        $pdo->exec('ALTER TABLE vault_credentials DROP INDEX idx_vault_credentials_operational');
    }

    if (!$columnExists('vault_credentials', 'secret_value')) {
        $pdo->exec('ALTER TABLE vault_credentials ADD COLUMN secret_value TEXT NOT NULL AFTER username');
    }
    foreach (['lock_version', 'vault_operation_id', 'vault_version', 'operational_state'] as $column) {
        if ($columnExists('vault_credentials', $column)) {
            $pdo->exec("ALTER TABLE vault_credentials DROP COLUMN `{$column}`");
        }
    }

    echo "Rollback estrutural concluido. A tabela vazia voltou ao formato anterior.\n";
})();
