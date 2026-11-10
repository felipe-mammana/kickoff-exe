<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';

$options = getopt('', ['credential-id:', 'apply']);
$credentialId = filter_var($options['credential-id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($credentialId)) {
    fwrite(STDERR, "Uso: php database/migrate_vault_secret_encryption.php --credential-id=ID [--apply]\n");
    exit(2);
}
if (APP_ENV !== 'homologacao' || DB_NAME !== 'kickoff_hml' || (string) db()->query('SELECT DATABASE()')->fetchColumn() !== 'kickoff_hml') {
    fwrite(STDERR, "Migracao recusada: destino nao e a homologacao kickoff_hml.\n");
    exit(3);
}

try {
    $service = VaultCredentialSecretService::fromApplication();
    $status = $service->encryptionStatus($credentialId);
    if (!array_key_exists('apply', $options)) {
        echo json_encode(['mode' => 'dry-run', 'credential_id' => $credentialId] + $status, JSON_THROW_ON_ERROR), PHP_EOL;
        exit(0);
    }
    if (!$status['encryption_enabled']) {
        throw new RuntimeException('VAULT_COFRE_ENVELOPE_ENCRYPTION deve estar habilitado.');
    }
    $result = $service->migrateEncryption($credentialId);
    echo json_encode(['mode' => 'apply', 'credential_id' => $credentialId] + $result, JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Migracao nao concluida: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
