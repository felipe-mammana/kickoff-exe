<?php

declare(strict_types=1);

class CredentialCrypto
{
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;
    private const AAD = 'exe-kickoff:credential:v1';

    public static function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (self::isEncrypted($value)) {
            return $value;
        }

        $iv = random_bytes(self::IV_BYTES);
        $tag = '';
        $encrypted = openssl_encrypt(
            $value,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            self::TAG_BYTES
        );

        if ($encrypted === false || strlen($tag) !== self::TAG_BYTES) {
            throw new RuntimeException('Não foi possível criptografar a credencial.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $encrypted);
    }

    public static function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '' || !self::isEncrypted($value)) {
            return $value;
        }

        $payload = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) <= self::IV_BYTES + self::TAG_BYTES) {
            return '[credencial inválida]';
        }

        $iv = substr($payload, 0, self::IV_BYTES);
        $tag = substr($payload, self::IV_BYTES, self::TAG_BYTES);
        $encrypted = substr($payload, self::IV_BYTES + self::TAG_BYTES);
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD
        );

        return $decrypted === false ? '[credencial inválida]' : $decrypted;
    }

    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && substr($value, 0, strlen(self::PREFIX)) === self::PREFIX;
    }

    private static function key(): string
    {
        if (!defined('APP_KEY') || APP_KEY === '') {
            throw new RuntimeException('APP_KEY não configurada. Gere uma chave antes de salvar credenciais.');
        }

        return hash('sha256', APP_KEY, true);
    }
}
