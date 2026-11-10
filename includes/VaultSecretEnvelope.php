<?php

declare(strict_types=1);

final class VaultSecretEnvelope
{
    public const FORMAT = 'enc:v2';
    public const ALGORITHM = 'AES-256-GCM';

    public function __construct(private readonly VaultTransitClient $transit)
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('Extensao OpenSSL obrigatoria para o cofre.');
        }
    }

    /** @return array<string, int|string> */
    public function encrypt(string $secret, int $companyId, int $credentialId, string $operationId, int $version): array
    {
        $this->validateContext($companyId, $credentialId, $operationId, $version);
        if ($secret === '' || strlen($secret) > 65536) {
            throw new InvalidArgumentException('Segredo vazio ou acima do limite permitido.');
        }
        $dataKey = $this->transit->generateWrappedDataKey();
        $key = $dataKey['plaintext'];
        $nonce = random_bytes(12);
        $tag = '';
        try {
            $ciphertext = openssl_encrypt(
                $secret,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                $this->aad($companyId, $credentialId, $operationId, $version),
                16
            );
        } finally {
            self::wipe($key);
            self::wipe($dataKey['plaintext']);
        }
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new VaultOperationException('encryption_failed', 'Nao foi possivel proteger o segredo.');
        }

        return [
            'format' => self::FORMAT,
            'algorithm' => self::ALGORITHM,
            'aad_version' => 1,
            'ciphertext' => base64_encode($ciphertext),
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'wrapped_dek' => $dataKey['ciphertext'],
        ];
    }

    /** @param array<string, mixed> $data */
    public function decrypt(array $data, int $companyId, int $credentialId, string $operationId, int $version): string
    {
        $this->validateContext($companyId, $credentialId, $operationId, $version);
        if (($data['format'] ?? null) !== self::FORMAT
            || ($data['algorithm'] ?? null) !== self::ALGORITHM
            || ($data['aad_version'] ?? null) !== 1
        ) {
            throw new VaultOperationException('unsupported_envelope', 'Formato criptografico do segredo nao suportado.');
        }
        foreach (['ciphertext', 'nonce', 'tag', 'wrapped_dek'] as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] === '') {
                throw new VaultOperationException('invalid_envelope', 'Envelope criptografico invalido.');
            }
        }
        $ciphertext = base64_decode($data['ciphertext'], true);
        $nonce = base64_decode($data['nonce'], true);
        $tag = base64_decode($data['tag'], true);
        if (!is_string($ciphertext) || strlen($ciphertext) > 65536
            || !is_string($nonce) || strlen($nonce) !== 12
            || !is_string($tag) || strlen($tag) !== 16
        ) {
            throw new VaultOperationException('invalid_envelope', 'Envelope criptografico invalido.');
        }
        $key = $this->transit->decryptDataKey($data['wrapped_dek']);
        try {
            $secret = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                $this->aad($companyId, $credentialId, $operationId, $version)
            );
        } finally {
            self::wipe($key);
        }
        if (!is_string($secret) || $secret === '' || strlen($secret) > 65536) {
            throw new VaultOperationException('integrity_check_failed', 'Integridade do segredo criptografado invalida.');
        }
        return $secret;
    }

    private function aad(int $companyId, int $credentialId, string $operationId, int $version): string
    {
        return json_encode([
            'format' => self::FORMAT,
            'aad_version' => 1,
            'company_id' => $companyId,
            'credential_id' => $credentialId,
            'operation_id' => $operationId,
            'vault_version' => $version,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function validateContext(int $companyId, int $credentialId, string $operationId, int $version): void
    {
        if ($companyId < 1 || $credentialId < 1 || $version < 1) {
            throw new InvalidArgumentException('Contexto criptografico invalido.');
        }
        VaultSecretPath::validateOperationId($operationId);
    }

    private static function wipe(string &$value): void
    {
        if ($value !== '' && function_exists('sodium_memzero')) {
            sodium_memzero($value);
        }
        $value = '';
    }
}
