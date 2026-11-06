<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/VaultHttpTransport.php';
require_once dirname(__DIR__) . '/includes/VaultOperationException.php';
require_once dirname(__DIR__) . '/includes/CurlVaultHttpTransport.php';
require_once dirname(__DIR__) . '/includes/VaultTokenProvider.php';
require_once dirname(__DIR__) . '/includes/VaultSecretPath.php';
require_once dirname(__DIR__) . '/includes/VaultWriteResult.php';
require_once dirname(__DIR__) . '/includes/VaultSecret.php';
require_once dirname(__DIR__) . '/includes/VaultKv2Client.php';

final class FakeVaultHttpTransport implements VaultHttpTransport
{
    /** @var list<array{status: int, headers: array<string, string>, body: string}|Throwable> */
    private array $responses = [];

    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /** @param array{status: int, headers: array<string, string>, body: string}|Throwable $response */
    public function queue(array|Throwable $response): void
    {
        $this->responses[] = $response;
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $connectTimeoutMs,
        int $requestTimeoutMs,
        string $caFile
    ): array {
        $this->requests[] = compact(
            'method',
            'url',
            'headers',
            'body',
            'connectTimeoutMs',
            'requestTimeoutMs',
            'caFile'
        );

        $response = array_shift($this->responses);
        if ($response instanceof Throwable) {
            throw $response;
        }
        if (!is_array($response)) {
            throw new RuntimeException('Fake sem resposta configurada.');
        }

        return $response;
    }
}

$checks = 0;

function check_vault_client(string $label, bool $condition): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('Falhou: ' . $label);
    }
    $checks++;
}

/** @return Throwable */
function expect_vault_exception(string $label, callable $callback, string $class, ?string $reason = null): Throwable
{
    try {
        $callback();
    } catch (Throwable $error) {
        check_vault_client($label . ': classe', $error instanceof $class);
        if ($reason !== null) {
            check_vault_client(
                $label . ': motivo',
                $error instanceof VaultOperationException && $error->reason() === $reason
            );
        }
        return $error;
    }

    throw new RuntimeException('Falhou: ' . $label . ' não lançou exceção.');
}

function response_vault(int $status, array $payload, array $headers = []): array
{
    return [
        'status' => $status,
        'headers' => $headers,
        'body' => json_encode($payload, JSON_THROW_ON_ERROR),
    ];
}

function write_response_vault(int $version): array
{
    return response_vault(200, [
        'data' => [
            'created_time' => '2026-10-19T20:00:00Z',
            'deletion_time' => '',
            'destroyed' => false,
            'version' => $version,
        ],
    ]);
}

function read_response_vault(int $companyId, int $credentialId, int $version, string $operationId, string $secret): array
{
    return response_vault(200, [
        'data' => [
            'data' => [
                'secret' => $secret,
                'company_id' => $companyId,
                'credential_id' => $credentialId,
                'operation_id' => $operationId,
                'schema_version' => 1,
            ],
            'metadata' => [
                'created_time' => '2026-10-19T20:00:00Z',
                'deletion_time' => '',
                'destroyed' => false,
                'version' => $version,
            ],
        ],
    ]);
}

$operationCreate = '11111111-1111-4111-8111-111111111111';
$operationUpdate = '22222222-2222-4222-8222-222222222222';
$secret = 'TEST-SECRET-DO-NOT-LOG';
$token = 'hvs.TEST-TOKEN-DO-NOT-LOG';
$tokenFile = tempnam(sys_get_temp_dir(), 'vault-token-test-');
$caFile = tempnam(sys_get_temp_dir(), 'vault-ca-test-');
$oversizedTokenFile = tempnam(sys_get_temp_dir(), 'vault-token-large-test-');

if ($tokenFile === false || $caFile === false || $oversizedTokenFile === false) {
    throw new RuntimeException('Não foi possível preparar os testes temporários.');
}

file_put_contents($tokenFile, $token . "\n");
file_put_contents($caFile, "TEST CA\n");
file_put_contents($oversizedTokenFile, str_repeat('A', 8193));

try {
    $tokenProvider = new VaultTokenProvider($tokenFile);
    check_vault_client('Token é lido sob demanda', $tokenProvider->token() === $token);
    file_put_contents($tokenFile, "hvs.REPLACED-TEST-TOKEN\n");
    check_vault_client('Token substituído pelo Agent é observado', $tokenProvider->token() === 'hvs.REPLACED-TEST-TOKEN');
    file_put_contents($tokenFile, $token . "\n");

    $transport = new FakeVaultHttpTransport();
    $client = new VaultKv2Client(
        $transport,
        $tokenProvider,
        new VaultSecretPath('kickoff-cofre-hml'),
        'https://10.10.10.30:8200',
        $caFile,
        750,
        2500
    );

    $transport->queue(write_response_vault(1));
    $created = $client->create(7, 45, $secret, $operationCreate);
    check_vault_client('Criação retorna versão', $created->version === 1);
    check_vault_client('Criação usa POST', $transport->requests[0]['method'] === 'POST');
    check_vault_client(
        'Criação usa caminho derivado',
        $transport->requests[0]['url'] === 'https://10.10.10.30:8200/v1/kickoff-cofre-hml/data/empresas/7/credenciais/45'
    );
    $createBody = json_decode((string) $transport->requests[0]['body'], true, 32, JSON_THROW_ON_ERROR);
    check_vault_client('Criação usa CAS zero', $createBody['options']['cas'] === 0);
    check_vault_client('Criação inclui identificadores', $createBody['data']['company_id'] === 7 && $createBody['data']['credential_id'] === 45);
    check_vault_client('Criação inclui operation_id', $createBody['data']['operation_id'] === $operationCreate);
    check_vault_client('Timeouts configurados', $transport->requests[0]['connectTimeoutMs'] === 750 && $transport->requests[0]['requestTimeoutMs'] === 2500);

    $transport->queue(read_response_vault(7, 45, 1, $operationCreate, $secret));
    $read = $client->read(7, 45, 1);
    check_vault_client('Leitura recupera segredo', $read->secret === $secret);
    check_vault_client('Leitura valida identificadores', $read->companyId === 7 && $read->credentialId === 45);
    check_vault_client('Leitura valida operation_id', $read->operationId === $operationCreate);
    check_vault_client('Leitura usa versão explícita', str_ends_with($transport->requests[1]['url'], '?version=1'));

    $transport->queue(write_response_vault(2));
    $updated = $client->update(7, 45, 'UPDATED-TEST-SECRET', 1, $operationUpdate);
    $updateBody = json_decode((string) $transport->requests[2]['body'], true, 32, JSON_THROW_ON_ERROR);
    check_vault_client('Atualização retorna nova versão', $updated->version === 2);
    check_vault_client('Atualização usa CAS esperado', $updateBody['options']['cas'] === 1);

    foreach ([
        fn () => $client->create(0, 45, $secret, $operationCreate),
        fn () => $client->create(7, 0, $secret, $operationCreate),
        fn () => $client->read(7, 45, 0),
        fn () => $client->update(7, 45, $secret, 0, $operationUpdate),
        fn () => $client->create(7, 45, $secret, 'not-an-operation-id'),
        fn () => $client->create(7, 45, $secret, '11111111-1111-1111-8111-111111111111'),
        fn () => $client->create(7, 45, $secret, '11111111-1111-7111-8111-111111111111'),
    ] as $index => $invalidCall) {
        expect_vault_exception('Identificador inválido ' . $index, $invalidCall, InvalidArgumentException::class);
    }

    expect_vault_exception(
        'Mount inválido',
        fn () => new VaultSecretPath('../other-mount'),
        InvalidArgumentException::class
    );
    expect_vault_exception(
        'Endereço sem TLS',
        fn () => new VaultKv2Client($transport, new VaultTokenProvider($tokenFile), new VaultSecretPath('mount'), 'http://vault:8200', $caFile),
        InvalidArgumentException::class
    );
    expect_vault_exception(
        'Timeout excessivo',
        fn () => new VaultKv2Client($transport, new VaultTokenProvider($tokenFile), new VaultSecretPath('mount'), 'https://vault:8200', $caFile, 1000, 30001),
        InvalidArgumentException::class
    );

    $unexpectedCreateVersionTransport = new FakeVaultHttpTransport();
    $unexpectedCreateVersionTransport->queue(write_response_vault(2));
    $unexpectedCreateVersionClient = new VaultKv2Client($unexpectedCreateVersionTransport, $tokenProvider, new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    expect_vault_exception(
        'Criação rejeita versão inicial inesperada',
        fn () => $unexpectedCreateVersionClient->create(7, 45, $secret, $operationCreate),
        VaultOperationException::class,
        'invalid_response'
    );
    check_vault_client('Criação inconsistente não é repetida', count($unexpectedCreateVersionTransport->requests) === 1);

    $unexpectedUpdateVersionTransport = new FakeVaultHttpTransport();
    $unexpectedUpdateVersionTransport->queue(write_response_vault(3));
    $unexpectedUpdateVersionClient = new VaultKv2Client($unexpectedUpdateVersionTransport, $tokenProvider, new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    expect_vault_exception(
        'Atualização rejeita versão divergente',
        fn () => $unexpectedUpdateVersionClient->update(7, 45, $secret, 1, $operationUpdate),
        VaultOperationException::class,
        'invalid_response'
    );
    check_vault_client('Atualização inconsistente não é repetida', count($unexpectedUpdateVersionTransport->requests) === 1);

    expect_vault_exception(
        'Token ausente',
        fn () => (new VaultTokenProvider($tokenFile . '.missing'))->token(),
        VaultOperationException::class,
        'token_unavailable'
    );
    $emptyTokenFile = tempnam(sys_get_temp_dir(), 'vault-token-empty-');
    if ($emptyTokenFile === false) throw new RuntimeException('Falha ao criar token temporário.');
    try {
        expect_vault_exception(
            'Token vazio',
            fn () => (new VaultTokenProvider($emptyTokenFile))->token(),
            VaultOperationException::class,
            'token_invalid'
        );
    } finally {
        unlink($emptyTokenFile);
    }
    expect_vault_exception(
        'Token ilegível',
        fn () => (new VaultTokenProvider($tokenFile, static fn () => false))->token(),
        VaultOperationException::class,
        'token_unavailable'
    );
    expect_vault_exception(
        'Token acima do limite',
        fn () => (new VaultTokenProvider($oversizedTokenFile))->token(),
        VaultOperationException::class,
        'token_invalid'
    );
    $throwingReader = new VaultTokenProvider($tokenFile, static function (): never {
        throw new RuntimeException('hvs.MUST-NOT-LEAK');
    });
    $readerError = expect_vault_exception(
        'Erro do leitor sanitizado',
        fn () => $throwingReader->token(),
        VaultOperationException::class,
        'token_unavailable'
    );
    check_vault_client('Exceção do leitor não expõe token', !str_contains($readerError->getMessage(), 'MUST-NOT-LEAK'));

    foreach ([
        403 => ['forbidden', false],
        404 => ['not_found', false],
        429 => ['rate_limited', true],
        500 => ['server_error', true],
    ] as $status => [$reason, $retryable]) {
        $errorTransport = new FakeVaultHttpTransport();
        $errorTransport->queue(response_vault($status, ['errors' => ['sensitive upstream detail']], ['retry-after' => '12']));
        $errorClient = new VaultKv2Client($errorTransport, new VaultTokenProvider($tokenFile), new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
        $error = expect_vault_exception(
            'HTTP ' . $status,
            fn () => $errorClient->create(7, 45, $secret, $operationCreate),
            VaultOperationException::class,
            $reason
        );
        check_vault_client('HTTP ' . $status . ' retryable', $error instanceof VaultOperationException && $error->isRetryable() === $retryable);
        check_vault_client('HTTP ' . $status . ' sem retry automático', count($errorTransport->requests) === 1);
        check_vault_client('HTTP ' . $status . ' não expõe segredo', !str_contains($error->getMessage(), $secret));
        check_vault_client('HTTP ' . $status . ' não expõe token', !str_contains($error->getMessage(), $token));
        if ($status === 429) {
            check_vault_client('HTTP 429 preserva Retry-After limitado', $error->retryAfterSeconds() === 12);
        }
    }

    foreach ([
        new VaultOperationException('tls_error', 'Falha na validação TLS do Vault.'),
        new VaultOperationException('timeout', 'Tempo limite de comunicação com o Vault excedido.', true),
        new VaultOperationException('communication_error', 'Falha de comunicação com o Vault.', true),
    ] as $transportError) {
        $errorTransport = new FakeVaultHttpTransport();
        $errorTransport->queue($transportError);
        $errorClient = new VaultKv2Client($errorTransport, new VaultTokenProvider($tokenFile), new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
        $error = expect_vault_exception(
            'Falha de transporte ' . $transportError->reason(),
            fn () => $errorClient->read(7, 45, 1),
            VaultOperationException::class,
            $transportError->reason()
        );
        check_vault_client('Falha de transporte sem retry', count($errorTransport->requests) === 1);
        check_vault_client('Falha de transporte sem segredo', !str_contains($error->getMessage(), $secret));
    }

    $casConflictTransport = new FakeVaultHttpTransport();
    $casConflictTransport->queue(response_vault(400, ['errors' => ['check-and-set parameter did not match the current version']]));
    $casConflictClient = new VaultKv2Client($casConflictTransport, $tokenProvider, new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    $casError = expect_vault_exception(
        'Conflito CAS classificado',
        fn () => $casConflictClient->update(7, 45, $secret, 1, $operationUpdate),
        VaultOperationException::class,
        'cas_conflict'
    );
    check_vault_client('Conflito CAS não é repetido', count($casConflictTransport->requests) === 1);
    check_vault_client('Conflito CAS não expõe corpo bruto', !str_contains($casError->getMessage(), 'check-and-set'));

    $validationErrorTransport = new FakeVaultHttpTransport();
    $validationErrorTransport->queue(response_vault(400, ['errors' => ['invalid payload supplied by test']]));
    $validationErrorClient = new VaultKv2Client($validationErrorTransport, $tokenProvider, new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    $validationError = expect_vault_exception(
        'Erro 400 de validação não é conflito CAS',
        fn () => $validationErrorClient->create(7, 45, $secret, $operationCreate),
        VaultOperationException::class,
        'validation_error'
    );
    check_vault_client('Erro 400 sanitizado', !str_contains($validationError->getMessage(), 'invalid payload'));
    check_vault_client('Erro 400 não é repetido', count($validationErrorTransport->requests) === 1);

    $invalidJsonTransport = new FakeVaultHttpTransport();
    $invalidJsonTransport->queue(['status' => 200, 'headers' => [], 'body' => '{invalid']);
    $invalidJsonClient = new VaultKv2Client($invalidJsonTransport, new VaultTokenProvider($tokenFile), new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    expect_vault_exception('JSON inválido', fn () => $invalidJsonClient->read(7, 45, 1), VaultOperationException::class, 'invalid_response');

    $missingFieldsTransport = new FakeVaultHttpTransport();
    $missingFieldsTransport->queue(response_vault(200, ['data' => ['metadata' => ['version' => 1]]]));
    $missingFieldsClient = new VaultKv2Client($missingFieldsTransport, new VaultTokenProvider($tokenFile), new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    expect_vault_exception('Campos obrigatórios ausentes', fn () => $missingFieldsClient->read(7, 45, 1), VaultOperationException::class, 'invalid_response');

    $wrongIdentityTransport = new FakeVaultHttpTransport();
    $wrongIdentityTransport->queue(read_response_vault(8, 45, 1, $operationCreate, $secret));
    $wrongIdentityClient = new VaultKv2Client($wrongIdentityTransport, new VaultTokenProvider($tokenFile), new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    expect_vault_exception('Empresa retornada divergente', fn () => $wrongIdentityClient->read(7, 45, 1), VaultOperationException::class, 'invalid_response');

    $wrongOperationTransport = new FakeVaultHttpTransport();
    $wrongOperationTransport->queue(read_response_vault(7, 45, 1, 'invalid-operation-id', $secret));
    $wrongOperationClient = new VaultKv2Client($wrongOperationTransport, new VaultTokenProvider($tokenFile), new VaultSecretPath('kickoff-cofre-hml'), 'https://vault.example:8200', $caFile);
    expect_vault_exception('Operation ID retornado inválido', fn () => $wrongOperationClient->read(7, 45, 1), VaultOperationException::class, 'invalid_response');

    if (extension_loaded('curl')) {
        expect_vault_exception(
            'CA ausente recusada pelo transporte',
            fn () => (new CurlVaultHttpTransport())->request('GET', 'https://vault.example:8200/v1/test', [], null, 100, 200, $caFile . '.missing'),
            VaultOperationException::class,
            'tls_configuration'
        );
    }

    echo 'OK: ' . $checks . " verificações do cliente Vault KV v2.\n";
} finally {
    unlink($tokenFile);
    unlink($caFile);
    unlink($oversizedTokenFile);
}
