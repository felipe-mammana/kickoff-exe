<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/VaultHttpTransport.php';
require dirname(__DIR__) . '/includes/VaultOperationException.php';
require dirname(__DIR__) . '/includes/VaultTokenProvider.php';
require dirname(__DIR__) . '/includes/VaultSecretPath.php';
require dirname(__DIR__) . '/includes/VaultWriteResult.php';
require dirname(__DIR__) . '/includes/VaultSecret.php';
require dirname(__DIR__) . '/includes/VaultTransitClient.php';
require dirname(__DIR__) . '/includes/VaultSecretEnvelope.php';
require dirname(__DIR__) . '/includes/VaultKv2Client.php';
require __DIR__ . '/fixtures/FakeVaultKv2Transport.php';

$failures = 0;
$check = static function (string $label, bool $condition) use (&$failures): void {
    echo ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$condition) $failures++;
};
$throws = static function (string $reason, callable $callback): bool {
    try {
        $callback();
    } catch (VaultOperationException $error) {
        return $error->reason() === $reason;
    }
    return false;
};

$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kickoff-vault-envelope-' . bin2hex(random_bytes(8));
if (!mkdir($directory, 0700) && !is_dir($directory)) throw new RuntimeException('Falha ao criar diretorio temporario.');
$tokenFile = $directory . DIRECTORY_SEPARATOR . 'token';
$caFile = $directory . DIRECTORY_SEPARATOR . 'ca.crt';
file_put_contents($tokenFile, str_repeat('t', 32));
file_put_contents($caFile, 'fake-ca');
chmod($tokenFile, 0600);

try {
    $transport = new FakeVaultKv2Transport();
    $provider = new VaultTokenProvider($tokenFile);
    $transit = new VaultTransitClient($transport, $provider, 'https://vault.example:8200', 'transit', 'kickoff-cofre-hml', $caFile);
    $envelope = new VaultSecretEnvelope($transit);
    $client = new VaultKv2Client(
        $transport, $provider, new VaultSecretPath('kickoff-cofre-hml'),
        'https://vault.example:8200', $caFile, 1000, 3000, $envelope, true
    );
    $op1 = '11111111-1111-4111-8111-111111111111';
    $op2 = '22222222-2222-4222-8222-222222222222';
    $secret1 = 'segredo-ficticio-envelope-1';
    $secret2 = 'segredo-ficticio-envelope-2';
    $write1 = $client->create(7, 41, $secret1, $op1);
    $stored1 = $transport->versionData(7, 41, 1);
    $check('Transit usa datakey/plaintext para receber DEK e ciphertext',
        in_array('POST /v1/transit/datakey/plaintext/kickoff-cofre-hml', $transport->requestPaths, true));
    $check('Transit nao usa datakey/wrapped incompatível com plaintext',
        !in_array('POST /v1/transit/datakey/wrapped/kickoff-cofre-hml', $transport->requestPaths, true));
    $check('Nova escrita usa schema_version 2', ($stored1['schema_version'] ?? null) === 2);
    $check('KV nao contem campo secret em texto legivel', !array_key_exists('secret', $stored1));
    $check('KV contem envelope enc:v2 AES-256-GCM', ($stored1['format'] ?? null) === 'enc:v2' && ($stored1['algorithm'] ?? null) === 'AES-256-GCM');
    $check('Ciphertext nao contem a senha', !str_contains((string) ($stored1['ciphertext'] ?? ''), $secret1));
    $check('DEK esta protegida pelo Transit', str_starts_with((string) ($stored1['wrapped_dek'] ?? ''), 'vault:v1:'));
    $read1 = $client->read(7, 41, $write1->version);
    $check('Leitura descriptografa a versao exata', $read1->secret === $secret1 && $read1->format === 'enc:v2');

    $write2 = $client->update(7, 41, $secret2, 1, $op2);
    $stored2 = $transport->versionData(7, 41, 2);
    $check('Atualizacao cria nova versao criptografada', $write2->version === 2 && $client->read(7, 41, 2)->secret === $secret2);
    $check('Versao historica continua legivel', $client->read(7, 41, 1)->secret === $secret1);
    $check('Nonce e DEK nao sao reutilizados entre versoes', $stored1['nonce'] !== $stored2['nonce'] && $stored1['wrapped_dek'] !== $stored2['wrapped_dek']);

    $tampered = $stored2;
    $rawCiphertext = base64_decode((string) $tampered['ciphertext'], true);
    $rawCiphertext[0] = chr(ord($rawCiphertext[0]) ^ 1);
    $tampered['ciphertext'] = base64_encode($rawCiphertext);
    $transport->replaceVersionData(7, 41, 2, $tampered);
    $check('Adulteracao do ciphertext falha na autenticacao', $throws('integrity_check_failed', fn () => $client->read(7, 41, 2)));
    $transport->replaceVersionData(7, 41, 2, $stored2);

    $tamperedTag = $stored2;
    $tag = base64_decode((string) $tamperedTag['tag'], true);
    $tag[0] = chr(ord($tag[0]) ^ 1);
    $tamperedTag['tag'] = base64_encode($tag);
    $transport->replaceVersionData(7, 41, 2, $tamperedTag);
    $check('Adulteracao da tag falha na autenticacao', $throws('integrity_check_failed', fn () => $client->read(7, 41, 2)));
    $transport->replaceVersionData(7, 41, 2, $stored2);

    $legacy = new VaultKv2Client($transport, $provider, new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    $legacyOperation = '33333333-3333-4333-8333-333333333333';
    $legacy->create(8, 42, 'segredo-legado-ficticio', $legacyOperation);
    $legacyRead = $client->read(8, 42, 1);
    $check('Cliente criptografado mantem leitura do formato legado', $legacyRead->secret === 'segredo-legado-ficticio' && $legacyRead->format === 'legacy:v1');
} finally {
    @unlink($tokenFile);
    @unlink($caFile);
    @rmdir($directory);
}

exit($failures === 0 ? 0 : 1);
