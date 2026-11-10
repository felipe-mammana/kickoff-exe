<?php

declare(strict_types=1);

final class VaultCredentialSecretService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly VaultKv2Client $vault
    ) {
    }

    public static function fromApplication(): self
    {
        return new self(db(), VaultKv2ClientFactory::create());
    }

    public function create(array $metadata, string $secret): int
    {
        if ($secret === '') {
            throw new InvalidArgumentException('O segredo e obrigatorio.');
        }

        $operationId = self::uuidV4();
        $encrypted = VaultCredential::encryptMetadata($metadata);
        $credentialId = 0;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO vault_credentials (
                    company_id, category_id, title, service_url, username,
                    operational_state, vault_version, vault_operation_id, lock_version,
                    notes, custom_fields, is_active, created_by, updated_by
                 ) VALUES (
                    :company_id, :category_id, :title, :service_url, :username,
                    'pending', NULL, NULL, 0,
                    :notes, :custom_fields, 1, :created_by, :updated_by
                 )"
            );
            $stmt->execute([
                'company_id' => $encrypted['company_id'],
                'category_id' => $encrypted['category_id'],
                'title' => $encrypted['title'],
                'service_url' => $encrypted['service_url'],
                'username' => $encrypted['username'],
                'notes' => $encrypted['notes'],
                'custom_fields' => $encrypted['custom_fields'],
                'created_by' => $encrypted['created_by'],
                'updated_by' => $encrypted['updated_by'],
            ]);
            $credentialId = (int) $this->pdo->lastInsertId();
            $this->insertOperation($operationId, $credentialId, 'create', 0, 0);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }

        $writeReturned = false;
        try {
            $result = $this->vault->create((int) $metadata['company_id'], $credentialId, $secret, $operationId);
            $writeReturned = true;
            $confirmed = $this->vault->read((int) $metadata['company_id'], $credentialId, $result->version);
            $this->assertConfirmation($confirmed, $operationId, $secret, 1);
            $this->markVaultConfirmed($operationId, $result->version);
            $this->finalizeCreate($credentialId, $operationId, $result->version);
            return $credentialId;
        } catch (VaultOperationException $error) {
            $ambiguous = $writeReturned || $error->isRetryable();
            $this->recordFailure($operationId, $error->reason(), !$ambiguous, $credentialId, true);
            throw new VaultCredentialServiceException(
                $ambiguous ? 'vault_write_unconfirmed' : $error->reason(),
                $ambiguous
                    ? 'A gravacao no Vault nao foi confirmada. Nao repita a operacao; ela ficou pendente para reconciliacao.'
                    : $error->getMessage(),
                $error->reason() === 'cas_conflict',
                $error
            );
        } catch (Throwable $error) {
            $this->recordFailure($operationId, 'MYSQL_FINALIZATION_ERROR', false, $credentialId, true);
            throw new VaultCredentialServiceException(
                'mysql_finalization_error',
                'O Vault pode ter recebido o segredo, mas o MySQL nao confirmou a operacao. Nao repita; execute a reconciliacao.',
                false,
                $error
            );
        }
    }

    /** @return array{secret_changed: bool, operation_id: ?string} */
    public function update(int $credentialId, array $metadata, ?string $secret, int $expectedLockVersion): array
    {
        $credential = $this->readyCredential($credentialId);
        if ((int) $credential['lock_version'] !== $expectedLockVersion) {
            throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial foi alterada por outra operacao.', true);
        }

        if ($secret === null || $secret === '') {
            $this->updateMetadata($credentialId, $metadata, $expectedLockVersion);
            return ['secret_changed' => false, 'operation_id' => null];
        }

        $operationId = self::uuidV4();
        $expectedVersion = (int) $credential['vault_version'];
        $this->pdo->beginTransaction();
        try {
            $locked = $this->lockCredential($credentialId);
            if (!$this->matchesReference($locked, $expectedVersion, $expectedLockVersion)) {
                throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial foi alterada por outra operacao.', true);
            }
            $this->insertOperation($operationId, $credentialId, 'update', $expectedVersion, $expectedLockVersion);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }

        $writeReturned = false;
        try {
            $result = $this->vault->update(
                (int) $credential['company_id'],
                $credentialId,
                $secret,
                $expectedVersion,
                $operationId
            );
            $writeReturned = true;
            $confirmed = $this->vault->read((int) $credential['company_id'], $credentialId, $result->version);
            $this->assertConfirmation($confirmed, $operationId, $secret, $expectedVersion + 1);
            $this->markVaultConfirmed($operationId, $result->version);
            $this->finalizeUpdate($credentialId, $operationId, $result->version, $expectedVersion, $expectedLockVersion, $metadata);
            return ['secret_changed' => true, 'operation_id' => $operationId];
        } catch (VaultOperationException $error) {
            $ambiguous = $writeReturned || $error->isRetryable();
            $this->recordFailure($operationId, $error->reason(), !$ambiguous, $credentialId, false);
            throw new VaultCredentialServiceException(
                $ambiguous ? 'vault_write_unconfirmed' : $error->reason(),
                $ambiguous
                    ? 'A atualizacao no Vault nao foi confirmada. Nao repita; execute a reconciliacao.'
                    : $error->getMessage(),
                $error->reason() === 'cas_conflict',
                $error
            );
        } catch (Throwable $error) {
            $this->recordFailure($operationId, 'MYSQL_FINALIZATION_ERROR', false, $credentialId, false);
            throw new VaultCredentialServiceException(
                'mysql_finalization_error',
                'A nova versao pode existir no Vault, mas o MySQL nao a confirmou. Nao repita; execute a reconciliacao.',
                false,
                $error
            );
        }
    }

    public function reveal(array $credential): string
    {
        if (($credential['operational_state'] ?? '') !== 'ready'
            || empty($credential['is_active'])
            || (int) ($credential['vault_version'] ?? 0) < 1
            || !is_string($credential['vault_operation_id'] ?? null)
        ) {
            throw new VaultCredentialServiceException('credential_not_ready', 'Credencial indisponivel ou ainda nao confirmada.');
        }

        try {
            $secret = $this->vault->read(
                (int) $credential['company_id'],
                (int) $credential['id'],
                (int) $credential['vault_version']
            );
        } catch (VaultOperationException $error) {
            throw new VaultCredentialServiceException($error->reason(), $error->getMessage(), false, $error);
        }
        if (!hash_equals((string) $credential['vault_operation_id'], $secret->operationId)) {
            throw new VaultCredentialServiceException('operation_mismatch', 'A referencia da credencial nao corresponde ao segredo no Vault.');
        }
        return $secret->secret;
    }

    /** @return array{migrated: bool, from: string, to: string, operation_id: ?string, vault_version: int} */
    public function migrateEncryption(int $credentialId): array
    {
        $credential = $this->readyCredential($credentialId);
        try {
            $current = $this->vault->read(
                (int) $credential['company_id'],
                $credentialId,
                (int) $credential['vault_version']
            );
        } catch (VaultOperationException $error) {
            throw new VaultCredentialServiceException($error->reason(), $error->getMessage(), false, $error);
        }
        if (!hash_equals((string) $credential['vault_operation_id'], $current->operationId)) {
            throw new VaultCredentialServiceException('operation_mismatch', 'A referencia da credencial nao corresponde ao segredo no Vault.');
        }
        if ($current->format === VaultSecretEnvelope::FORMAT) {
            return [
                'migrated' => false, 'from' => $current->format, 'to' => $current->format,
                'operation_id' => null, 'vault_version' => $current->version,
            ];
        }
        if (!$this->vault->encryptionEnabled()) {
            throw new VaultCredentialServiceException('encryption_disabled', 'Criptografia Transit nao habilitada para a migracao.');
        }

        $operationId = self::uuidV4();
        $expectedVersion = (int) $credential['vault_version'];
        $expectedLock = (int) $credential['lock_version'];
        $this->pdo->beginTransaction();
        try {
            $locked = $this->lockCredential($credentialId);
            if (!$this->matchesReference($locked, $expectedVersion, $expectedLock)) {
                throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial foi alterada por outra operacao.', true);
            }
            $this->insertOperation($operationId, $credentialId, 'update', $expectedVersion, $expectedLock);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }

        $writeReturned = false;
        try {
            $result = $this->vault->update(
                (int) $credential['company_id'], $credentialId, $current->secret, $expectedVersion, $operationId
            );
            $writeReturned = true;
            $confirmed = $this->vault->read((int) $credential['company_id'], $credentialId, $result->version);
            $this->assertConfirmation($confirmed, $operationId, $current->secret, $expectedVersion + 1);
            $this->markVaultConfirmed($operationId, $result->version);
            $this->finalizeSecretRewrite($credentialId, $operationId, $result->version, $expectedVersion, $expectedLock);
            return [
                'migrated' => true, 'from' => $current->format, 'to' => $confirmed->format,
                'operation_id' => $operationId, 'vault_version' => $result->version,
            ];
        } catch (VaultOperationException $error) {
            $ambiguous = $writeReturned || $error->isRetryable();
            $this->recordFailure($operationId, $error->reason(), !$ambiguous, $credentialId, false);
            throw new VaultCredentialServiceException(
                $ambiguous ? 'vault_write_unconfirmed' : $error->reason(),
                $ambiguous
                    ? 'A migracao pode ter criado uma versao no Vault. Nao repita; execute a reconciliacao.'
                    : $error->getMessage(),
                $error->reason() === 'cas_conflict',
                $error
            );
        } catch (Throwable $error) {
            $this->recordFailure($operationId, 'MYSQL_FINALIZATION_ERROR', false, $credentialId, false);
            throw new VaultCredentialServiceException(
                'mysql_finalization_error',
                'A versao criptografada pode existir no Vault, mas o MySQL nao confirmou. Execute a reconciliacao.',
                false,
                $error
            );
        }
    }

    /** @return array{format: string, vault_version: int, encryption_enabled: bool} */
    public function encryptionStatus(int $credentialId): array
    {
        $credential = $this->readyCredential($credentialId);
        try {
            $secret = $this->vault->read(
                (int) $credential['company_id'], $credentialId, (int) $credential['vault_version']
            );
        } catch (VaultOperationException $error) {
            throw new VaultCredentialServiceException($error->reason(), $error->getMessage(), false, $error);
        }
        if (!hash_equals((string) $credential['vault_operation_id'], $secret->operationId)) {
            throw new VaultCredentialServiceException('operation_mismatch', 'A referencia da credencial nao corresponde ao segredo no Vault.');
        }
        return [
            'format' => $secret->format,
            'vault_version' => $secret->version,
            'encryption_enabled' => $this->vault->encryptionEnabled(),
        ];
    }

    public function deactivate(int $credentialId, int $userId, int $expectedLockVersion): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vault_credentials
             SET is_active = 0, updated_by = :updated_by, lock_version = lock_version + 1
             WHERE id = :id AND is_active = 1 AND lock_version = :lock_version'
        );
        $stmt->execute(['id' => $credentialId, 'updated_by' => $userId, 'lock_version' => $expectedLockVersion]);
        if ($stmt->rowCount() !== 1) {
            throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial foi alterada por outra operacao.', true);
        }
    }

    /** @return array{completed: int, failed: int, pending: int, audit_failed: int} */
    public function reconcilePending(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT o.*, v.company_id, v.operational_state, v.vault_version,
                    v.vault_operation_id, v.lock_version
             FROM vault_secret_operations o
             INNER JOIN vault_credentials v ON v.id = o.credential_id
             WHERE o.operation_state IN ('pending', 'vault_confirmed')
             ORDER BY o.id
             LIMIT :limit"
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $counts = ['completed' => 0, 'failed' => 0, 'pending' => 0, 'audit_failed' => 0];

        foreach ($stmt->fetchAll() as $operation) {
            $resultVersion = (int) $operation['expected_vault_version'] + 1;
            try {
                $secret = $this->vault->read(
                    (int) $operation['company_id'],
                    (int) $operation['credential_id'],
                    $resultVersion
                );
                if (!hash_equals((string) $operation['operation_id'], $secret->operationId)) {
                    $this->markOperationFailed((string) $operation['operation_id'], 'OPERATION_MISMATCH');
                    $counts['failed']++;
                    continue;
                }
                $this->markVaultConfirmed((string) $operation['operation_id'], $resultVersion);
                $this->finalizeReconciled($operation, $resultVersion);
                $counts['completed']++;
                try {
                    AuditLog::record([
                        'required' => true,
                        'action_type' => 'vault_operation_reconciled',
                        'affected_table' => 'vault_credentials',
                        'affected_record_id' => (int) $operation['credential_id'],
                        'company_id' => (int) $operation['company_id'],
                        'description' => 'Operacao do cofre reconciliada sem repetir a escrita no Vault.',
                        'new_data' => ['operation_id' => $operation['operation_id'], 'vault_version' => $resultVersion],
                    ]);
                } catch (Throwable) {
                    $counts['audit_failed']++;
                }
            } catch (VaultOperationException $error) {
                if ($error->reason() === 'not_found') {
                    $this->markOperationFailed((string) $operation['operation_id'], 'VAULT_VERSION_NOT_FOUND');
                    if ($operation['operation_type'] === 'create') {
                        $this->markCredentialError((int) $operation['credential_id']);
                    }
                    $counts['failed']++;
                } else {
                    $this->recordFailure((string) $operation['operation_id'], $error->reason(), false, (int) $operation['credential_id'], false);
                    $counts['pending']++;
                }
            } catch (Throwable) {
                $counts['pending']++;
            }
        }

        return $counts;
    }

    private function insertOperation(string $operationId, int $credentialId, string $type, int $expectedVersion, int $expectedLock): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vault_secret_operations
                (operation_id, credential_id, operation_type, operation_state,
                 expected_vault_version, result_vault_version, expected_lock_version)
             VALUES (:operation_id, :credential_id, :operation_type, 'pending',
                     :expected_version, NULL, :expected_lock)"
        );
        $stmt->execute([
            'operation_id' => $operationId,
            'credential_id' => $credentialId,
            'operation_type' => $type,
            'expected_version' => $expectedVersion,
            'expected_lock' => $expectedLock,
        ]);
    }

    private function finalizeCreate(int $credentialId, string $operationId, int $version): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE vault_credentials
                 SET operational_state = 'ready', vault_version = :version,
                     vault_operation_id = :operation_id, lock_version = lock_version + 1
                 WHERE id = :id AND operational_state = 'pending' AND lock_version = 0"
            );
            $stmt->execute(['version' => $version, 'operation_id' => $operationId, 'id' => $credentialId]);
            if ($stmt->rowCount() !== 1) {
                throw new VaultCredentialServiceException('optimistic_conflict', 'O cadastro foi alterado durante a confirmacao.', true);
            }
            $this->completeOperation($operationId, $version);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }
    }

    private function finalizeUpdate(int $credentialId, string $operationId, int $version, int $expectedVersion, int $expectedLock, array $metadata): void
    {
        $encrypted = VaultCredential::encryptMetadata($metadata);
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE vault_credentials SET
                    category_id = :category_id, title = :title, service_url = :service_url,
                    username = :username, notes = :notes, custom_fields = :custom_fields,
                    updated_by = :updated_by, vault_version = :version,
                    vault_operation_id = :operation_id, lock_version = lock_version + 1
                 WHERE id = :id AND operational_state = 'ready'
                   AND vault_version = :expected_version AND lock_version = :expected_lock"
            );
            $stmt->execute([
                'category_id' => $encrypted['category_id'], 'title' => $encrypted['title'],
                'service_url' => $encrypted['service_url'], 'username' => $encrypted['username'],
                'notes' => $encrypted['notes'], 'custom_fields' => $encrypted['custom_fields'],
                'updated_by' => $encrypted['updated_by'], 'version' => $version,
                'operation_id' => $operationId, 'id' => $credentialId,
                'expected_version' => $expectedVersion, 'expected_lock' => $expectedLock,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial foi alterada durante a confirmacao.', true);
            }
            $this->completeOperation($operationId, $version);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }
    }

    private function finalizeSecretRewrite(
        int $credentialId,
        string $operationId,
        int $version,
        int $expectedVersion,
        int $expectedLock
    ): void {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE vault_credentials
                 SET vault_version = :version, vault_operation_id = :operation_id,
                     lock_version = lock_version + 1
                 WHERE id = :id AND operational_state = 'ready'
                   AND vault_version = :expected_version AND lock_version = :expected_lock"
            );
            $stmt->execute([
                'version' => $version, 'operation_id' => $operationId, 'id' => $credentialId,
                'expected_version' => $expectedVersion, 'expected_lock' => $expectedLock,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial mudou durante a migracao.', true);
            }
            $this->completeOperation($operationId, $version);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }
    }

    private function finalizeReconciled(array $operation, int $version): void
    {
        $this->pdo->beginTransaction();
        try {
            $credential = $this->lockCredential((int) $operation['credential_id']);
            if ((int) ($credential['vault_version'] ?? 0) === $version
                && hash_equals((string) ($credential['vault_operation_id'] ?? ''), (string) $operation['operation_id'])
            ) {
                $this->completeOperation((string) $operation['operation_id'], $version);
                $this->pdo->commit();
                return;
            }

            $expectedVersion = (int) $operation['expected_vault_version'];
            $canFinalize = $operation['operation_type'] === 'create'
                ? in_array($credential['operational_state'], ['pending', 'error'], true) && $credential['vault_version'] === null
                : $credential['operational_state'] === 'ready' && (int) $credential['vault_version'] === $expectedVersion;
            if (!$canFinalize) {
                throw new VaultCredentialServiceException('reconciliation_conflict', 'A referencia MySQL avancou para outra versao.', true);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE vault_credentials
                 SET operational_state = 'ready', vault_version = :version,
                     vault_operation_id = :operation_id, lock_version = lock_version + 1
                 WHERE id = :id AND lock_version = :current_lock"
            );
            $stmt->execute([
                'version' => $version, 'operation_id' => $operation['operation_id'],
                'id' => $operation['credential_id'], 'current_lock' => $credential['lock_version'],
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new VaultCredentialServiceException('reconciliation_conflict', 'A credencial mudou durante a reconciliacao.', true);
            }
            $this->completeOperation((string) $operation['operation_id'], $version);
            $this->pdo->commit();
        } catch (Throwable $error) {
            $this->rollBack();
            throw $error;
        }
    }

    private function updateMetadata(int $credentialId, array $metadata, int $expectedLock): void
    {
        $encrypted = VaultCredential::encryptMetadata($metadata);
        $stmt = $this->pdo->prepare(
            "UPDATE vault_credentials SET
                category_id = :category_id, title = :title, service_url = :service_url,
                username = :username, notes = :notes, custom_fields = :custom_fields,
                updated_by = :updated_by, lock_version = lock_version + 1
             WHERE id = :id AND operational_state = 'ready' AND lock_version = :expected_lock"
        );
        $stmt->execute([
            'category_id' => $encrypted['category_id'], 'title' => $encrypted['title'],
            'service_url' => $encrypted['service_url'], 'username' => $encrypted['username'],
            'notes' => $encrypted['notes'], 'custom_fields' => $encrypted['custom_fields'],
            'updated_by' => $encrypted['updated_by'], 'id' => $credentialId,
            'expected_lock' => $expectedLock,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new VaultCredentialServiceException('optimistic_conflict', 'A credencial foi alterada por outra operacao.', true);
        }
    }

    private function readyCredential(int $id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM vault_credentials WHERE id = :id AND operational_state = 'ready' AND is_active = 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new VaultCredentialServiceException('credential_not_ready', 'Credencial nao encontrada ou indisponivel.');
        }
        return $row;
    }

    private function lockCredential(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vault_credentials WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new VaultCredentialServiceException('credential_not_found', 'Credencial nao encontrada.');
        }
        return $row;
    }

    private function matchesReference(array $credential, int $version, int $lockVersion): bool
    {
        return $credential['operational_state'] === 'ready'
            && !empty($credential['is_active'])
            && (int) $credential['vault_version'] === $version
            && (int) $credential['lock_version'] === $lockVersion;
    }

    private function assertConfirmation(VaultSecret $confirmed, string $operationId, string $secret, int $version): void
    {
        if ($confirmed->version !== $version
            || !hash_equals($operationId, $confirmed->operationId)
            || !hash_equals($secret, $confirmed->secret)
            || ($this->vault->encryptionEnabled() && $confirmed->format !== VaultSecretEnvelope::FORMAT)
        ) {
            throw new VaultOperationException('confirmation_mismatch', 'A confirmacao do Vault nao corresponde a escrita realizada.');
        }
    }

    private function markVaultConfirmed(string $operationId, int $version): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vault_secret_operations
             SET operation_state = 'vault_confirmed', result_vault_version = :version,
                 attempts = attempts + 1, last_attempt_at = CURRENT_TIMESTAMP,
                 error_code = NULL, next_attempt_at = NULL
             WHERE operation_id = :operation_id AND operation_state IN ('pending', 'vault_confirmed')"
        );
        $stmt->execute(['version' => $version, 'operation_id' => $operationId]);
    }

    private function completeOperation(string $operationId, int $version): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vault_secret_operations
             SET operation_state = 'completed', result_vault_version = :version,
                 completed_at = CURRENT_TIMESTAMP, error_code = NULL, next_attempt_at = NULL
             WHERE operation_id = :operation_id AND operation_state IN ('pending', 'vault_confirmed', 'completed')"
        );
        $stmt->execute(['version' => $version, 'operation_id' => $operationId]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Operacao do ledger nao pode ser concluida.');
        }
    }

    private function recordFailure(string $operationId, string $reason, bool $final, int $credentialId, bool $create): void
    {
        try {
            if ($final) {
                $this->markOperationFailed($operationId, self::errorCode($reason));
                if ($create) {
                    $this->markCredentialError($credentialId);
                }
                return;
            }
            $stmt = $this->pdo->prepare(
                "UPDATE vault_secret_operations
                 SET attempts = attempts + 1, last_attempt_at = CURRENT_TIMESTAMP,
                     next_attempt_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 5 MINUTE),
                     error_code = :error_code
                 WHERE operation_id = :operation_id AND operation_state IN ('pending', 'vault_confirmed')"
            );
            $stmt->execute(['error_code' => self::errorCode($reason), 'operation_id' => $operationId]);
        } catch (Throwable) {
            // A falha original prevalece; nunca registrar payloads ou excecoes brutas.
        }
    }

    private function markOperationFailed(string $operationId, string $errorCode): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vault_secret_operations
             SET operation_state = 'failed', result_vault_version = NULL,
                 attempts = attempts + 1, last_attempt_at = CURRENT_TIMESTAMP,
                 error_code = :error_code, next_attempt_at = NULL
             WHERE operation_id = :operation_id AND operation_state IN ('pending', 'vault_confirmed')"
        );
        $stmt->execute(['error_code' => self::errorCode($errorCode), 'operation_id' => $operationId]);
    }

    private function markCredentialError(int $credentialId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE vault_credentials SET operational_state = 'error'
             WHERE id = :id AND operational_state = 'pending'
               AND vault_version IS NULL AND vault_operation_id IS NULL"
        );
        $stmt->execute(['id' => $credentialId]);
    }

    private function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    private static function errorCode(string $reason): string
    {
        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $reason));
        return substr(trim($code, '_'), 0, 64) ?: 'UNKNOWN_ERROR';
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
