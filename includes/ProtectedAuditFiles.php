<?php
declare(strict_types=1);

class ProtectedAuditFiles
{
    public static function directory(): string
    {
        return STORAGE_PATH . (APP_ENV === 'testing' ? '/protected-audit/testing' : '/protected-audit');
    }

    public static function append(array $event): void
    {
        unset($event['session_identifier']);
        $directory = self::directory();
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Arquivo de auditoria indisponivel.');
        // Each line is an authenticated encrypted event, not plaintext disguised by extension.
        $record = ['recorded_at' => gmdate('c'), 'event' => AuditRedactor::clean($event)];
        $line = EncryptedBackup::encrypt(json_encode($record, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'audit') . "\n";
        if (file_put_contents($directory . '/' . gmdate('Y-m-d') . '.txt.enc', $line, FILE_APPEND | LOCK_EX) !== strlen($line)) throw new RuntimeException('Falha no arquivo de auditoria.');
    }

    public static function read(string $date): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) throw new RuntimeException('Data invalida.');
        $path = self::directory() . '/' . $date . '.txt.enc';
        if (!is_file($path)) return 'Nenhum registro nesta data.';
        if (filesize($path) > 20 * 1024 * 1024) throw new RuntimeException('Arquivo excede o limite de consulta.');
        $handle = fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('Falha na leitura.');
        $text = '';
        try {
            if (!flock($handle, LOCK_SH)) throw new RuntimeException('Arquivo ocupado.');
            while (($line = fgets($handle)) !== false) {
                $text .= EncryptedBackup::decrypt(trim($line), 'audit') . "\n";
            }
        } finally { fclose($handle); }
        return $text;
    }
}
