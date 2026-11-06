<?php

declare(strict_types=1);

final class VaultKv2Client
{
    private const MAX_SECRET_BYTES = 65536;
    private const MAX_RESPONSE_BYTES = 131072;

    private readonly string $address;

    public function __construct(
        private readonly VaultHttpTransport $transport,
        private readonly VaultTokenProvider $tokenProvider,
        private readonly VaultSecretPath $paths,
        string $address,
        private readonly string $caFile,
        private readonly int $connectTimeoutMs = 1000,
        private readonly int $requestTimeoutMs = 3000
    ) {
        $parts = parse_url($address);
        if (
            !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new InvalidArgumentException('Endereço do Vault inválido.');
        }
        if (trim($caFile) === '') {
            throw new InvalidArgumentException('Certificado CA do Vault não configurado.');
        }
        if ($connectTimeoutMs < 100 || $connectTimeoutMs > 10000) {
            throw new InvalidArgumentException('Timeout de conexão do Vault fora do limite permitido.');
        }
        if ($requestTimeoutMs < $connectTimeoutMs || $requestTimeoutMs > 30000) {
            throw new InvalidArgumentException('Timeout de operação do Vault fora do limite permitido.');
        }

        $this->address = rtrim($address, '/');
    }

    public function create(
        int $companyId,
        int $credentialId,
        string $secret,
        string $operationId
    ): VaultWriteResult {
        return $this->write($companyId, $credentialId, $secret, 0, $operationId);
    }

    public function read(int $companyId, int $credentialId, int $version): VaultSecret
    {
        if ($version < 1) {
            throw new InvalidArgumentException('Versão do segredo no Vault inválida.');
        }

        $response = $this->request(
            'GET',
            $this->paths->dataPath($companyId, $credentialId) . '?version=' . $version,
            null
        );
        $payload = $this->decode($response['body']);
        $data = $payload['data']['data'] ?? null;
        $metadata = $payload['data']['metadata'] ?? null;

        if (!is_array($data) || !is_array($metadata)) {
            throw $this->invalidResponse();
        }

        $secret = $data['secret'] ?? null;
        $returnedCompanyId = $data['company_id'] ?? null;
        $returnedCredentialId = $data['credential_id'] ?? null;
        $operationId = $data['operation_id'] ?? null;
        $schemaVersion = $data['schema_version'] ?? null;
        $returnedVersion = $metadata['version'] ?? null;
        $destroyed = $metadata['destroyed'] ?? null;
        $deletionTime = $metadata['deletion_time'] ?? null;

        if (
            !is_string($secret)
            || $secret === ''
            || strlen($secret) > self::MAX_SECRET_BYTES
            || !is_int($returnedCompanyId)
            || !is_int($returnedCredentialId)
            || !is_string($operationId)
            || $schemaVersion !== 1
            || !is_int($returnedVersion)
            || !is_bool($destroyed)
            || !is_string($deletionTime)
            || $returnedCompanyId !== $companyId
            || $returnedCredentialId !== $credentialId
            || $returnedVersion !== $version
            || $destroyed
            || $deletionTime !== ''
        ) {
            throw $this->invalidResponse();
        }

        try {
            VaultSecretPath::validateOperationId($operationId);
        } catch (InvalidArgumentException $error) {
            throw $this->invalidResponse($error);
        }

        return new VaultSecret($secret, $companyId, $credentialId, $operationId, $version);
    }

    public function update(
        int $companyId,
        int $credentialId,
        string $secret,
        int $expectedVersion,
        string $operationId
    ): VaultWriteResult {
        if ($expectedVersion < 1) {
            throw new InvalidArgumentException('Versão esperada do segredo no Vault inválida.');
        }

        return $this->write($companyId, $credentialId, $secret, $expectedVersion, $operationId);
    }

    private function write(
        int $companyId,
        int $credentialId,
        string $secret,
        int $cas,
        string $operationId
    ): VaultWriteResult {
        $path = $this->paths->dataPath($companyId, $credentialId);
        $operationId = VaultSecretPath::validateOperationId($operationId);
        if ($secret === '' || strlen($secret) > self::MAX_SECRET_BYTES) {
            throw new InvalidArgumentException('Segredo vazio ou acima do limite permitido.');
        }
        if ($cas === PHP_INT_MAX) {
            throw new InvalidArgumentException('Versão CAS fora do limite permitido.');
        }
        $expectedVersion = $cas + 1;

        try {
            $body = json_encode([
                'options' => ['cas' => $cas],
                'data' => [
                    'secret' => $secret,
                    'company_id' => $companyId,
                    'credential_id' => $credentialId,
                    'operation_id' => $operationId,
                    'schema_version' => 1,
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $error) {
            throw new VaultOperationException('invalid_request', 'Não foi possível preparar a requisição ao Vault.', false, null, null, $error);
        }

        $response = $this->request('POST', $path, $body);
        $payload = $this->decode($response['body']);
        $metadata = $payload['data'] ?? null;
        $version = is_array($metadata) ? ($metadata['version'] ?? null) : null;
        $createdTime = is_array($metadata) ? ($metadata['created_time'] ?? null) : null;
        $destroyed = is_array($metadata) ? ($metadata['destroyed'] ?? null) : null;
        $deletionTime = is_array($metadata) ? ($metadata['deletion_time'] ?? null) : null;

        if (
            !is_int($version)
            || $version !== $expectedVersion
            || !is_string($createdTime)
            || trim($createdTime) === ''
            || !is_bool($destroyed)
            || !is_string($deletionTime)
            || $destroyed
            || $deletionTime !== ''
        ) {
            throw $this->invalidResponse();
        }

        return new VaultWriteResult($version, $createdTime);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function request(string $method, string $path, ?string $body): array
    {
        $token = $this->tokenProvider->token();
        $response = $this->transport->request(
            $method,
            $this->address . $path,
            [
                'Accept: application/json',
                'Content-Type: application/json',
                'X-Vault-Token: ' . $token,
            ],
            $body,
            $this->connectTimeoutMs,
            $this->requestTimeoutMs,
            $this->caFile
        );

        $status = $response['status'] ?? 0;
        if (!is_int($status) || !isset($response['headers'], $response['body']) || !is_array($response['headers']) || !is_string($response['body'])) {
            throw $this->invalidResponse();
        }
        if (strlen($response['body']) > self::MAX_RESPONSE_BYTES) {
            throw new VaultOperationException('response_body_too_large', 'Resposta recebida do Vault excede o limite permitido.');
        }

        if ($status >= 200 && $status < 300) {
            return $response;
        }

        throw $this->httpFailure($status, $response['headers'], $response['body']);
    }

    /** @return array<string, mixed> */
    private function decode(string $body): array
    {
        if (strlen($body) > self::MAX_RESPONSE_BYTES) {
            throw new VaultOperationException('response_body_too_large', 'Resposta recebida do Vault excede o limite permitido.');
        }

        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw $this->invalidResponse($error);
        }

        if (!is_array($decoded)) {
            throw $this->invalidResponse();
        }

        return $decoded;
    }

    /** @param array<string, string> $headers */
    private function httpFailure(int $status, array $headers, string $body): VaultOperationException
    {
        if ($status === 400) {
            if ($this->isCasConflict($body)) {
                return new VaultOperationException('cas_conflict', 'A versão do segredo foi alterada por outra operação.', false, 400);
            }
            return new VaultOperationException('validation_error', 'Requisição ao Vault rejeitada.', false, 400);
        }

        return match (true) {
            $status === 403 => new VaultOperationException('forbidden', 'Acesso ao Vault recusado.', false, 403),
            $status === 404 => new VaultOperationException('not_found', 'Segredo não encontrado no Vault.', false, 404),
            $status === 429 => new VaultOperationException(
                'rate_limited',
                'Limite de requisições ao Vault excedido.',
                true,
                429,
                $this->retryAfter($headers['retry-after'] ?? null)
            ),
            $status >= 500 && $status <= 599 => new VaultOperationException('server_error', 'Vault temporariamente indisponível.', true, $status),
            default => new VaultOperationException('unexpected_status', 'Resposta HTTP inesperada do Vault.', false, $status > 0 ? $status : null),
        };
    }

    private function isCasConflict(string $body): bool
    {
        try {
            $payload = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        $errors = is_array($payload) ? ($payload['errors'] ?? null) : null;
        if (!is_array($errors)) {
            return false;
        }

        foreach ($errors as $error) {
            if (is_string($error) && strtolower(trim($error)) === 'check-and-set parameter did not match the current version') {
                return true;
            }
        }
        return false;
    }

    private function retryAfter(?string $value): ?int
    {
        if ($value === null || preg_match('/^[0-9]{1,5}$/D', $value) !== 1) {
            return null;
        }

        return min(300, (int) $value);
    }

    private function invalidResponse(?Throwable $previous = null): VaultOperationException
    {
        return new VaultOperationException('invalid_response', 'Resposta inválida recebida do Vault.', false, null, null, $previous);
    }
}
