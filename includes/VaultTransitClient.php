<?php

declare(strict_types=1);

final class VaultTransitClient
{
    private const MAX_RESPONSE_BYTES = 32768;

    private readonly string $address;
    private readonly string $mount;
    private readonly string $keyName;

    public function __construct(
        private readonly VaultHttpTransport $transport,
        private readonly VaultTokenProvider $tokenProvider,
        string $address,
        string $mount,
        string $keyName,
        private readonly string $caFile,
        private readonly int $connectTimeoutMs = 1000,
        private readonly int $requestTimeoutMs = 3000
    ) {
        $parts = parse_url($address);
        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new InvalidArgumentException('Endereco do Vault Transit invalido.');
        }
        foreach (['mount' => $mount, 'key' => $keyName] as $label => $value) {
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/D', $value) !== 1) {
                throw new InvalidArgumentException('Identificador do Vault Transit invalido: ' . $label . '.');
            }
        }
        if ($caFile === '' || $connectTimeoutMs < 100 || $connectTimeoutMs > 10000
            || $requestTimeoutMs < $connectTimeoutMs || $requestTimeoutMs > 30000
        ) {
            throw new InvalidArgumentException('Configuracao do Vault Transit invalida.');
        }

        $this->address = rtrim($address, '/');
        $this->mount = $mount;
        $this->keyName = $keyName;
    }

    /** @return array{plaintext: string, ciphertext: string} */
    public function generateWrappedDataKey(): array
    {
        $payload = $this->request(
            '/v1/' . rawurlencode($this->mount) . '/datakey/wrapped/' . rawurlencode($this->keyName),
            ['bits' => 256]
        );
        $plaintext = $payload['data']['plaintext'] ?? null;
        $ciphertext = $payload['data']['ciphertext'] ?? null;
        if (!is_string($plaintext) || !is_string($ciphertext) || !str_starts_with($ciphertext, 'vault:v')) {
            throw $this->invalidResponse();
        }
        $decoded = base64_decode($plaintext, true);
        self::wipe($plaintext);
        if (isset($payload['data']['plaintext']) && is_string($payload['data']['plaintext'])) {
            self::wipe($payload['data']['plaintext']);
        }
        if (!is_string($decoded) || strlen($decoded) !== 32 || strlen($ciphertext) > 8192) {
            throw $this->invalidResponse();
        }

        return ['plaintext' => $decoded, 'ciphertext' => $ciphertext];
    }

    public function decryptDataKey(string $ciphertext): string
    {
        if (!str_starts_with($ciphertext, 'vault:v') || strlen($ciphertext) > 8192) {
            throw new VaultOperationException('invalid_envelope', 'Envelope criptografico invalido.');
        }
        $payload = $this->request(
            '/v1/' . rawurlencode($this->mount) . '/decrypt/' . rawurlencode($this->keyName),
            ['ciphertext' => $ciphertext]
        );
        $plaintext = $payload['data']['plaintext'] ?? null;
        if (!is_string($plaintext)) {
            throw $this->invalidResponse();
        }
        $decoded = base64_decode($plaintext, true);
        self::wipe($plaintext);
        if (isset($payload['data']['plaintext']) && is_string($payload['data']['plaintext'])) {
            self::wipe($payload['data']['plaintext']);
        }
        if (!is_string($decoded) || strlen($decoded) !== 32) {
            throw $this->invalidResponse();
        }

        return $decoded;
    }

    /** @param array<string, mixed> $body @return array<string, mixed> */
    private function request(string $path, array $body): array
    {
        $response = $this->transport->request(
            'POST',
            $this->address . $path,
            ['Accept: application/json', 'Content-Type: application/json', 'X-Vault-Token: ' . $this->tokenProvider->token()],
            json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $this->connectTimeoutMs,
            $this->requestTimeoutMs,
            $this->caFile
        );
        $status = $response['status'] ?? 0;
        $raw = $response['body'] ?? null;
        if (!is_int($status) || !is_string($raw) || strlen($raw) > self::MAX_RESPONSE_BYTES) {
            throw $this->invalidResponse();
        }
        if ($status < 200 || $status >= 300) {
            throw match (true) {
                $status === 403 => new VaultOperationException('transit_forbidden', 'Acesso ao Vault Transit recusado.', false, 403),
                $status === 404 => new VaultOperationException('transit_not_found', 'Chave ou mount do Vault Transit nao encontrado.', false, 404),
                $status === 429 => new VaultOperationException('transit_rate_limited', 'Limite do Vault Transit excedido.', true, 429),
                $status >= 500 => new VaultOperationException('transit_unavailable', 'Vault Transit temporariamente indisponivel.', true, $status),
                default => new VaultOperationException('transit_error', 'Operacao do Vault Transit rejeitada.', false, $status ?: null),
            };
        }
        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            self::wipe($raw);
            if (isset($response['body']) && is_string($response['body'])) {
                self::wipe($response['body']);
            }
            throw $this->invalidResponse($error);
        }
        self::wipe($raw);
        if (isset($response['body']) && is_string($response['body'])) {
            self::wipe($response['body']);
        }
        if (!is_array($decoded)) {
            throw $this->invalidResponse();
        }
        return $decoded;
    }

    private function invalidResponse(?Throwable $previous = null): VaultOperationException
    {
        return new VaultOperationException('transit_invalid_response', 'Resposta invalida recebida do Vault Transit.', false, null, null, $previous);
    }

    private static function wipe(string &$value): void
    {
        if ($value !== '' && function_exists('sodium_memzero')) {
            sodium_memzero($value);
        }
        $value = '';
    }
}
