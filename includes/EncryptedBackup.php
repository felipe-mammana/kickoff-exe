<?php
declare(strict_types=1);

class EncryptedBackup
{
    public static function encrypt(string $data, string $type): string
    {
        $encrypted = (string) CredentialCrypto::encrypt(json_encode(['format' => 'exe-backup-v1', 'type' => $type, 'data' => base64_encode($data)], JSON_THROW_ON_ERROR));
        if (!str_starts_with($encrypted, 'enc:v2:')) throw new RuntimeException('Configure as chaves versionadas antes de exportar.');
        return $encrypted;
    }

    public static function decrypt(string $data, string $type): string
    {
        if (!str_starts_with($data, 'enc:v2:')) throw new RuntimeException('Backup criptografado invalido.');
        $payload = json_decode((string) CredentialCrypto::decrypt($data), true, 512, JSON_THROW_ON_ERROR);
        if (($payload['format'] ?? '') !== 'exe-backup-v1' || ($payload['type'] ?? '') !== $type) throw new RuntimeException('Tipo de backup incorreto.');
        $plain = base64_decode($payload['data'] ?? '', true);
        if ($plain === false) throw new RuntimeException('Conteudo invalido.');
        return $plain;
    }
}
