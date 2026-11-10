<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (getenv('VAULT_KV2_TEST_DISPOSABLE') !== 'YES_I_UNDERSTAND') {
    fwrite(STDERR, "Teste recusado antes da conexao: defina VAULT_KV2_TEST_DISPOSABLE=YES_I_UNDERSTAND.\n");
    exit(2);
}

$dbHost = (string) getenv('DB_HOST');
$dbUser = (string) getenv('DB_USER');
$dbPassValue = getenv('DB_PASS');
$dbPass = $dbPassValue === false ? '' : (string) $dbPassValue;
$dbPort = (string) (getenv('DB_PORT') ?: '3306');
$targetMode = strtolower(trim((string) (getenv('VAULT_KV2_TEST_TARGET') ?: 'loopback')));
$dockerInspectMode = strtolower(trim((string) (getenv('VAULT_KV2_TEST_DOCKER_INSPECT') ?: 'direct')));
$dockerTarget = null;

if ($dbUser === '' || $dbPort !== '3306') {
    fwrite(STDERR, "Teste recusado: DB_USER deve ser informado e DB_PORT deve ser 3306.\n");
    exit(2);
}
if ((getenv('DB_NAME') !== false && trim((string) getenv('DB_NAME')) !== '')
    || (getenv('TEST_DB_NAME') !== false && trim((string) getenv('TEST_DB_NAME')) !== '')
) {
    fwrite(STDERR, "Teste recusado: DB_NAME e TEST_DB_NAME nao podem ser fornecidos; o nome descartavel e gerado internamente.\n");
    exit(2);
}

$inspectDockerTarget = static function (string $identifier, string $host, string $inspectMode): array {
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D', $identifier) !== 1) {
        throw new RuntimeException('Identificador de container Docker invalido.');
    }
    if (filter_var($host, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException('No modo Docker, DB_HOST deve ser um IP pertencente ao container inspecionado.');
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $command = match ($inspectMode) {
        'direct' => ['docker', 'inspect', '--type', 'container', $identifier],
        'sudo-n' => ['/usr/bin/sudo', '-n', '/usr/bin/docker', 'inspect', '--type', 'container', $identifier],
        default => throw new RuntimeException(
            'VAULT_KV2_TEST_DOCKER_INSPECT deve ser direct ou sudo-n.'
        ),
    };
    $process = @proc_open(
        $command,
        $descriptors,
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Nao foi possivel executar docker inspect.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = microtime(true) + 5.0;
    $status = null;

    try {
        do {
            $stdout .= (string) stream_get_contents($pipes[1], 1048576 - strlen($stdout));
            $stderr .= (string) stream_get_contents($pipes[2], 65536 - strlen($stderr));
            if (strlen($stdout) >= 1048576 || strlen($stderr) >= 65536) {
                proc_terminate($process);
                throw new RuntimeException('Saida de docker inspect excedeu o limite permitido.');
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process);
                throw new RuntimeException('docker inspect excedeu o tempo limite.');
            }
            usleep(20000);
        } while (true);

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
    } finally {
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closeCode = proc_close($process);
    }

    $exitCode = is_array($status) && is_int($status['exitcode']) && $status['exitcode'] >= 0
        ? $status['exitcode']
        : $closeCode;
    if ($exitCode !== 0) {
        throw new RuntimeException('docker inspect nao confirmou o container descartavel informado.');
    }

    try {
        $decoded = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        throw new RuntimeException('Resposta invalida de docker inspect.', 0, $error);
    }
    if (!is_array($decoded) || count($decoded) !== 1 || !is_array($decoded[0])) {
        throw new RuntimeException('docker inspect nao retornou exatamente um container.');
    }

    $container = $decoded[0];
    $containerId = strtolower((string) ($container['Id'] ?? ''));
    $containerName = ltrim((string) ($container['Name'] ?? ''), '/');
    $expected = strtolower($identifier);
    $identifierMatches = hash_equals(strtolower($containerName), $expected)
        || (strlen($expected) >= 12 && str_starts_with($containerId, $expected));
    if (preg_match('/^[0-9a-f]{64}$/D', $containerId) !== 1 || !$identifierMatches) {
        throw new RuntimeException('A identidade retornada pelo Docker nao corresponde ao identificador esperado.');
    }
    if (($container['State']['Running'] ?? false) !== true) {
        throw new RuntimeException('O container Docker autorizado nao esta em execucao.');
    }

    $configuredAddress = inet_pton($host);
    $addressMatches = false;
    foreach ((array) ($container['NetworkSettings']['Networks'] ?? []) as $network) {
        if (!is_array($network)) {
            continue;
        }
        foreach (['IPAddress', 'GlobalIPv6Address'] as $field) {
            $candidate = (string) ($network[$field] ?? '');
            if ($candidate !== '' && inet_pton($candidate) === $configuredAddress) {
                $addressMatches = true;
                break 2;
            }
        }
    }
    if (!$addressMatches) {
        throw new RuntimeException('DB_HOST nao pertence a nenhuma rede do container Docker autorizado.');
    }

    $hostname = (string) ($container['Config']['Hostname'] ?? '');
    if ($hostname === '' || strlen($hostname) > 255) {
        throw new RuntimeException('Hostname interno do container Docker invalido.');
    }

    return ['id' => $containerId, 'name' => $containerName, 'hostname' => $hostname];
};

try {
    if ($targetMode === 'loopback') {
        if (!in_array(strtolower($dbHost), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('DB_HOST deve identificar o MySQL em loopback.');
        }
    } elseif ($targetMode === 'docker') {
        $containerIdentifier = trim((string) getenv('VAULT_KV2_TEST_CONTAINER'));
        if ($containerIdentifier === '') {
            throw new RuntimeException('Defina VAULT_KV2_TEST_CONTAINER com o nome ou ID esperado.');
        }
        $dockerTarget = $inspectDockerTarget($containerIdentifier, $dbHost, $dockerInspectMode);
    } else {
        throw new RuntimeException('VAULT_KV2_TEST_TARGET deve ser loopback ou docker.');
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Teste recusado antes da conexao: ' . $error->getMessage() . "\n");
    exit(2);
}

$root = dirname(__DIR__);
$testDb = 'kickoff_vault_kv2_test_' . bin2hex(random_bytes(8));
$quotedDb = '`' . $testDb . '`';
$createdByThisTest = false;
$testTokenFile = null;
$passed = 0;
$failed = 0;
$exitCode = 1;

$check = static function (string $label, bool $condition) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "[OK] {$label}\n";
        return;
    }
    $failed++;
    echo "[ERRO] {$label}\n";
};
$expectPdoFailure = static function (string $label, callable $callback) use ($check): void {
    try {
        $callback();
        $check($label, false);
    } catch (PDOException $exception) {
        $check($label, true);
    }
};

try {
    $serverPdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $serverIdentity = $serverPdo->query(
        'SELECT @@hostname AS server_hostname, DATABASE() AS selected_database'
    )->fetch();
    if (!is_array($serverIdentity) || $serverIdentity['selected_database'] !== null) {
        throw new RuntimeException('A conexao de teste nao pode iniciar com um banco selecionado.');
    }
    if (is_array($dockerTarget)
        && !hash_equals((string) $dockerTarget['hostname'], (string) ($serverIdentity['server_hostname'] ?? ''))
    ) {
        throw new RuntimeException('O servidor MySQL conectado nao corresponde ao hostname do container inspecionado.');
    }

    $exists = $serverPdo->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = :database_name'
    );
    $exists->execute(['database_name' => $testDb]);
    if ((int) $exists->fetchColumn() !== 0) {
        throw new RuntimeException('Nome aleatorio ja existe; o teste recusou reutiliza-lo.');
    }

    $serverPdo->exec("CREATE DATABASE {$quotedDb} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $createdByThisTest = true;

    putenv('APP_ENV=testing');
    putenv('APP_DEBUG=false');
    putenv('APP_KEY=test-only-vault-kv2-database-key');
    putenv('DB_HOST=' . $dbHost);
    putenv('DB_NAME=' . $testDb);
    putenv('DB_USER=' . $dbUser);
    putenv('DB_PASS=' . $dbPass);
    putenv('DB_CHARSET=utf8mb4');
    putenv('VAULT_KV2_DISPOSABLE_TEST=1');

    require_once $root . '/includes/bootstrap.php';
    $pdo = db();
    $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    $pdo->exec('CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE companies (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE vault_categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
    $pdo->exec(
        'CREATE TABLE vault_credentials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            company_id INT UNSIGNED NOT NULL,
            category_id INT UNSIGNED NULL,
            title VARCHAR(160) NOT NULL,
            service_url VARCHAR(255) NULL,
            username VARCHAR(190) NULL,
            secret_value TEXT NOT NULL,
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
            CONSTRAINT fk_test_vault_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            CONSTRAINT fk_test_vault_category FOREIGN KEY (category_id) REFERENCES vault_categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_test_vault_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_test_vault_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB'
    );
    $pdo->exec('INSERT INTO users () VALUES ()');
    $pdo->exec("INSERT INTO companies (name) VALUES ('Empresa descartavel')");

    require $root . '/database/apply_vault_kv2_migration.php';
    require $root . '/database/apply_vault_kv2_migration.php';

    $columns = $pdo->query(
        "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, DATA_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vault_credentials'"
    )->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
    $check('Schema nao possui secret_value', !isset($columns['secret_value']));
    foreach (['operational_state', 'vault_version', 'vault_operation_id', 'lock_version'] as $column) {
        $check("Coluna definitiva {$column}", isset($columns[$column]));
    }
    foreach (['secret_backend', 'vault_path', 'shadow_vault_version', 'migration_state'] as $column) {
        $check("Coluna legada ausente: {$column}", !isset($columns[$column]));
    }
    $check('Default operacional pending', ($columns['operational_state']['COLUMN_DEFAULT'] ?? null) === 'pending');
    $check('Default lock_version zero', (string) ($columns['lock_version']['COLUMN_DEFAULT'] ?? '') === '0');
    $ledgerColumns = $pdo->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vault_secret_operations'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $check('Ledger nao possui campos para segredo, token ou resposta bruta',
        count(array_intersect($ledgerColumns, ['secret', 'secret_value', 'token', 'payload', 'request_body', 'response_body'])) === 0
    );

    $pdo->exec("INSERT INTO vault_credentials (company_id, title) VALUES (1, 'Pendente ativa')");
    $pending = $pdo->query('SELECT operational_state, vault_version, vault_operation_id, lock_version, is_active FROM vault_credentials WHERE id = 1')->fetch();
    $check('Cadastro pendente representavel e independente de is_active',
        $pending['operational_state'] === 'pending'
        && $pending['vault_version'] === null
        && $pending['vault_operation_id'] === null
        && (int) $pending['lock_version'] === 0
        && (int) $pending['is_active'] === 1
    );

    $validCreateUuid = '123e4567-e89b-42d3-a456-426614174000';
    $validUpdateUuid = '123e4567-e89b-42d3-b456-426614174001';
    $pdo->prepare("UPDATE vault_credentials SET operational_state = 'ready', vault_version = 1, vault_operation_id = :uuid, is_active = 0 WHERE id = 1")
        ->execute(['uuid' => $validCreateUuid]);
    $check('Credencial pronta pode estar logicamente inativa', (int) $pdo->query('SELECT is_active FROM vault_credentials WHERE id = 1')->fetchColumn() === 0);

    $expectPdoFailure('UUID em maiusculas rejeitado', static function () use ($pdo): void {
        $pdo->exec("UPDATE vault_credentials SET vault_operation_id = '123E4567-E89B-42D3-A456-426614174000' WHERE id = 1");
    });
    $expectPdoFailure('UUID que nao e v4 rejeitado', static function () use ($pdo): void {
        $pdo->exec("UPDATE vault_credentials SET vault_operation_id = '123e4567-e89b-32d3-a456-426614174000' WHERE id = 1");
    });
    $expectPdoFailure('Estado ready sem referencia rejeitado', static function () use ($pdo): void {
        $pdo->exec("INSERT INTO vault_credentials (company_id, title, operational_state) VALUES (1, 'Invalida', 'ready')");
    });
    $expectPdoFailure('Estado error com versao rejeitado', static function () use ($pdo, $validCreateUuid): void {
        $stmt = $pdo->prepare("INSERT INTO vault_credentials (company_id, title, operational_state, vault_version, vault_operation_id) VALUES (1, 'Invalida', 'error', 1, :uuid)");
        $stmt->execute(['uuid' => $validCreateUuid]);
    });

    $updated = $pdo->exec('UPDATE vault_credentials SET title = title, lock_version = lock_version + 1 WHERE id = 1 AND lock_version = 0');
    $conflict = $pdo->exec('UPDATE vault_credentials SET title = title, lock_version = lock_version + 1 WHERE id = 1 AND lock_version = 0');
    $check('Controle otimista detecta concorrencia', $updated === 1 && $conflict === 0);

    $insertOperation = $pdo->prepare(
        'INSERT INTO vault_secret_operations
            (operation_id, credential_id, operation_type, operation_state, expected_vault_version, result_vault_version, expected_lock_version, completed_at)
         VALUES
            (:operation_id, 1, :operation_type, :operation_state, :expected_version, :result_version, :lock_version, :completed_at)'
    );
    $insertOperation->execute([
        'operation_id' => $validCreateUuid,
        'operation_type' => 'create',
        'operation_state' => 'completed',
        'expected_version' => 0,
        'result_version' => 1,
        'lock_version' => 0,
        'completed_at' => '2026-01-01 00:00:00',
    ]);
    $insertOperation->execute([
        'operation_id' => $validUpdateUuid,
        'operation_type' => 'update',
        'operation_state' => 'pending',
        'expected_version' => 1,
        'result_version' => null,
        'lock_version' => 1,
        'completed_at' => null,
    ]);
    $check('Ledger representa create concluido e update pendente', (int) $pdo->query('SELECT COUNT(*) FROM vault_secret_operations')->fetchColumn() === 2);

    $expectPdoFailure('Create exige CAS zero', static function () use ($pdo): void {
        $pdo->exec("INSERT INTO vault_secret_operations (operation_id, credential_id, operation_type, expected_vault_version, expected_lock_version) VALUES ('123e4567-e89b-42d3-a456-426614174010', 1, 'create', 1, 0)");
    });
    $expectPdoFailure('Update exige versao atual positiva', static function () use ($pdo): void {
        $pdo->exec("INSERT INTO vault_secret_operations (operation_id, credential_id, operation_type, expected_vault_version, expected_lock_version) VALUES ('123e4567-e89b-42d3-a456-426614174011', 1, 'update', 0, 0)");
    });
    $expectPdoFailure('Versao resultante deve ser a seguinte', static function () use ($pdo): void {
        $pdo->exec("INSERT INTO vault_secret_operations (operation_id, credential_id, operation_type, operation_state, expected_vault_version, result_vault_version, expected_lock_version) VALUES ('123e4567-e89b-42d3-a456-426614174012', 1, 'update', 'vault_confirmed', 1, 3, 1)");
    });
    $expectPdoFailure('Falha exige codigo sanitizado', static function () use ($pdo): void {
        $pdo->exec("INSERT INTO vault_secret_operations (operation_id, credential_id, operation_type, operation_state, expected_vault_version, expected_lock_version) VALUES ('123e4567-e89b-42d3-a456-426614174013', 1, 'update', 'failed', 1, 1)");
    });
    $expectPdoFailure('Ledger rejeita chave estrangeira inexistente', static function () use ($pdo): void {
        $pdo->exec("INSERT INTO vault_secret_operations (operation_id, credential_id, operation_type, expected_vault_version, expected_lock_version) VALUES ('123e4567-e89b-42d3-a456-426614174014', 999, 'create', 0, 0)");
    });

    require_once $root . '/tests/fixtures/FakeVaultKv2Transport.php';
    $testTokenFile = tempnam(sys_get_temp_dir(), 'vault-token-test-');
    if (!is_string($testTokenFile) || file_put_contents($testTokenFile, 'test-token-not-secret') === false) {
        throw new RuntimeException('Nao foi possivel preparar o token ficticio.');
    }
    $fakeTransport = new FakeVaultKv2Transport();
    $fakeClient = new VaultKv2Client(
        $fakeTransport,
        new VaultTokenProvider($testTokenFile),
        new VaultSecretPath('kickoff-cofre-hml'),
        'https://vault.invalid:8200',
        $testTokenFile,
        100,
        500
    );
    $service = new VaultCredentialSecretService($pdo, $fakeClient);
    $metadata = [
        'company_id' => 1, 'category_id' => null, 'title' => 'Servico Vault',
        'service_url' => 'https://example.invalid', 'username' => 'tester',
        'notes' => 'nota protegida', 'custom_fields' => '{"ambiente":"teste"}',
        'created_by' => null, 'updated_by' => null,
    ];
    $serviceCredentialId = $service->create($metadata, 'segredo-ficticio-1');
    $serviceRow = $pdo->query('SELECT * FROM vault_credentials WHERE id = ' . $serviceCredentialId)->fetch();
    $check('Servico confirma cadastro na versao 1', $serviceRow['operational_state'] === 'ready'
        && (int) $serviceRow['vault_version'] === 1 && (int) $serviceRow['lock_version'] === 1);
    $check('Notas permanecem criptografadas no MySQL', CredentialCrypto::isEncrypted($serviceRow['notes']));
    $check('Segredo nao existe nas colunas da credencial', !array_key_exists('secret_value', $serviceRow));

    $metadata['title'] = 'Servico Vault editado';
    $metadata['updated_by'] = null;
    $metadataOnly = $service->update($serviceCredentialId, $metadata, null, 1);
    $check('Atualizacao vazia preserva segredo e altera somente metadados', !$metadataOnly['secret_changed']);
    $secretUpdate = $service->update($serviceCredentialId, $metadata, 'segredo-ficticio-2', 2);
    $updatedServiceRow = $pdo->query('SELECT * FROM vault_credentials WHERE id = ' . $serviceCredentialId)->fetch();
    $check('Atualizacao usa nova versao Vault e lock otimista', $secretUpdate['secret_changed']
        && (int) $updatedServiceRow['vault_version'] === 2 && (int) $updatedServiceRow['lock_version'] === 3);
    $check('Revelacao usa a versao exata confirmada', $service->reveal($updatedServiceRow) === 'segredo-ficticio-2');

    $fakeTransport->conflictNextWrite();
    try {
        $service->update($serviceCredentialId, $metadata, 'nao-gravado', 3);
        $check('Conflito CAS e rejeitado', false);
    } catch (VaultCredentialServiceException $exception) {
        $check('Conflito CAS e rejeitado', $exception->isConflict());
    }
    $afterCas = $pdo->query('SELECT vault_version, lock_version FROM vault_credentials WHERE id = ' . $serviceCredentialId)->fetch();
    $check('Conflito CAS preserva referencia anterior', (int) $afterCas['vault_version'] === 2 && (int) $afterCas['lock_version'] === 3);

    $service->deactivate($serviceCredentialId, 1, 3);
    $check('Desativacao e somente logica', (int) $pdo->query('SELECT is_active FROM vault_credentials WHERE id = ' . $serviceCredentialId)->fetchColumn() === 0);

    $fakeTransport->timeoutAfterNextWrite();
    try {
        $service->create(array_merge($metadata, ['title' => 'Timeout ambiguo']), 'segredo-timeout');
        $check('Timeout ambiguo nao confirma sucesso', false);
    } catch (VaultCredentialServiceException $exception) {
        $check('Timeout ambiguo nao confirma sucesso', $exception->reason() === 'vault_write_unconfirmed');
    }
    $pendingBefore = (int) $pdo->query("SELECT COUNT(*) FROM vault_secret_operations WHERE operation_state = 'pending'")->fetchColumn();
    $reconciled = $service->reconcilePending(50);
    $check('Reconciliacao confirma escrita por leitura sem repetir POST', $pendingBefore > 0 && $reconciled['completed'] >= 1);

    $missingTokenClient = new VaultKv2Client(
        new FakeVaultKv2Transport(),
        new VaultTokenProvider($testTokenFile . '.missing'),
        new VaultSecretPath('kickoff-cofre-hml'),
        'https://vault.invalid:8200',
        $testTokenFile,
        100,
        500
    );
    try {
        (new VaultCredentialSecretService($pdo, $missingTokenClient))->create(
            array_merge($metadata, ['title' => 'Token ausente']),
            'segredo-nao-enviado'
        );
        $check('Token ausente indisponibiliza somente a operacao do cofre', false);
    } catch (VaultCredentialServiceException $exception) {
        $check('Token ausente indisponibiliza somente a operacao do cofre', $exception->reason() === 'token_unavailable');
    }

    try {
        require $root . '/database/rollback_vault_kv2_migration.php';
        $check('Rollback bloqueado com dependencias', false);
    } catch (RuntimeException $exception) {
        $check('Rollback bloqueado com dependencias', str_contains($exception->getMessage(), 'vault_credentials'));
    }

    $pdo->exec('DELETE FROM vault_secret_operations');
    $pdo->exec('DELETE FROM vault_credentials');
    require $root . '/database/rollback_vault_kv2_migration.php';
    $afterRollback = $pdo->query(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'vault_credentials'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $check('Rollback restaura secret_value apenas na tabela vazia', in_array('secret_value', $afterRollback, true));
    $check('Rollback remove estrutura Vault', !in_array('operational_state', $afterRollback, true)
        && !in_array('vault_version', $afterRollback, true)
        && !in_array('vault_operation_id', $afterRollback, true)
        && !in_array('lock_version', $afterRollback, true));

    $pdo->exec("INSERT INTO vault_credentials (company_id, title, secret_value) VALUES (1, 'Bloqueio', 'valor-ficticio')");
    try {
        require $root . '/database/apply_vault_kv2_migration.php';
        $check('Instalacao bloqueada com registro existente', false);
    } catch (RuntimeException $exception) {
        $check('Instalacao bloqueada com registro existente', str_contains($exception->getMessage(), 'Nenhum dado foi removido'));
    }
    $preserved = $pdo->query("SELECT secret_value FROM vault_credentials WHERE title = 'Bloqueio'")->fetchColumn();
    $check('Bloqueio preserva dados existentes', $preserved === 'valor-ficticio');

    echo "Resultado: {$passed} verificacoes aprovadas; {$failed} falhas.\n";
    $exitCode = $failed === 0 ? 0 : 1;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha do teste: ' . $exception->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if (is_string($testTokenFile) && is_file($testTokenFile)) {
        @unlink($testTokenFile);
    }
    if ($createdByThisTest) {
        try {
            $serverPdo->exec("DROP DATABASE {$quotedDb}");
            echo "Banco descartavel {$testDb} removido.\n";
        } catch (Throwable $exception) {
            fwrite(STDERR, "ATENCAO: remova manualmente somente o banco {$testDb}.\n");
            $exitCode = 1;
        }
    }
}

exit($exitCode);
