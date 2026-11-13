<?php

declare(strict_types=1);

final class VaultImportPreview
{
    private const SESSION_KEY = 'vault_import_preview';
    private const TTL_SECONDS = 900;
    private const CLAIM_TTL_SECONDS = 600;

    public static function store(array $records, int $ownerUserId, array $metadata = []): string
    {
        if ($ownerUserId < 1) {
            throw new InvalidArgumentException('Usuário inválido para a prévia.');
        }
        self::clear();
        $token = bin2hex(random_bytes(24));
        $now = time();
        $payload = json_encode([
            'token' => $token,
            'owner_user_id' => $ownerUserId,
            'created_at' => $now,
            'expires_at' => $now + self::TTL_SECONDS,
            'state' => 'review',
            'records' => array_values($records),
            'metadata' => $metadata,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        try {
            $_SESSION[self::SESSION_KEY] = CredentialCrypto::encrypt($payload, 'vault_import_preview');
        } catch (Throwable $error) {
            throw new VaultImportPreviewCryptoException('Serviço criptográfico indisponível.', 0, $error);
        }
        return $token;
    }

    public static function load(?string $token = null, ?int $expectedUserId = null): ?array
    {
        $payload = self::decode($token, $expectedUserId);
        return $payload;
    }

    /** Atomically claims the preview for one confirmation request. */
    public static function claim(string $token, int $expectedUserId): array
    {
        $payload = self::decode($token, $expectedUserId);
        if ($payload === null) {
            throw new VaultImportPreviewInvalidException('Prévia inválida ou expirada.');
        }
        $now = time();
        if (($payload['state'] ?? 'review') === 'confirming'
            && (int) ($payload['claim_expires_at'] ?? 0) > $now
        ) {
            throw new VaultImportPreviewBusyException('A prévia já está sendo processada.');
        }

        $payload['state'] = 'confirming';
        $payload['claimed_at'] = $now;
        $payload['claim_expires_at'] = $now + self::CLAIM_TTL_SECONDS;
        try {
            $_SESSION[self::SESSION_KEY] = CredentialCrypto::encrypt(
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'vault_import_preview'
            );
        } catch (Throwable $error) {
            throw new VaultImportPreviewCryptoException('Serviço criptográfico indisponível.', 0, $error);
        }
        return $payload;
    }

    private static function decode(?string $token, ?int $expectedUserId): ?array
    {
        $encrypted = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $plain = CredentialCrypto::decrypt($encrypted, 'vault_import_preview');
        } catch (Throwable $error) {
            throw new VaultImportPreviewCryptoException('Serviço criptográfico indisponível.', 0, $error);
        }
        if (!is_string($plain) || $plain === '[credencial inválida]') {
            self::clear();
            return null;
        }

        $payload = json_decode($plain, true);
        if (!is_array($payload) || (int) ($payload['expires_at'] ?? 0) < time()) {
            self::clear();
            return null;
        }
        if ($expectedUserId !== null && ($expectedUserId < 1 || (int) ($payload['owner_user_id'] ?? 0) !== $expectedUserId)) {
            return null;
        }
        if ($token !== null && (!is_string($payload['token'] ?? null) || !hash_equals($payload['token'], $token))) {
            return null;
        }

        return $payload;
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }
}

final class VaultImportPreviewCryptoException extends RuntimeException
{
}

final class VaultImportPreviewInvalidException extends RuntimeException
{
}

final class VaultImportPreviewBusyException extends RuntimeException
{
}
