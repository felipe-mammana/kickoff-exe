<?php
declare(strict_types=1);

/** Authenticated envelope; independent key rings per purpose. */
final class VersionedCrypto
{
    public static function encrypt(string $plain, string $purpose, string $id, array $keys): string
    {
        self::validateLabel($purpose);
        self::validateLabel($id);
        $key = self::key($id, $keys);
        $iv = random_bytes(12);
        $aad = 'exe:enc:v2:' . $purpose . ':' . $id;
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
        if ($cipher === false) throw new RuntimeException('Encryption failed');
        return 'enc:v2:' . $purpose . ':' . $id . ':' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $envelope, string $purpose, array $keys): string
    {
        $parts = explode(':', $envelope, 5);
        if (count($parts) !== 5 || $parts[0] !== 'enc' || $parts[1] !== 'v2' || $parts[2] !== $purpose) {
            throw new RuntimeException('Invalid encrypted envelope');
        }
        self::validateLabel($purpose);
        self::validateLabel($parts[3]);
        $raw = base64_decode($parts[4], true);
        if ($raw === false || strlen($raw) < 28) throw new RuntimeException('Invalid encrypted payload');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key($parts[3], $keys),
            OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16),
            'exe:enc:v2:' . $purpose . ':' . $parts[3]);
        if ($plain === false) throw new RuntimeException('Encrypted data authentication failed');
        return $plain;
    }

    private static function validateLabel(string $label): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,48}$/D', $label)) throw new RuntimeException('Invalid key label');
    }

    private static function key(string $id, array $keys): string
    {
        $key = base64_decode((string) ($keys[$id] ?? ''), true);
        if ($key === false || strlen($key) !== 32) throw new RuntimeException('Encryption key unavailable');
        return $key;
    }
}
