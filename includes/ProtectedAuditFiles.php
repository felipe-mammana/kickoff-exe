<?php
declare(strict_types=1);

class ProtectedAuditFiles
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

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
        $records = self::readRecords($date);
        if (!$records) return 'Nenhum registro nesta data.';

        $lines = [];
        foreach ($records as $record) {
            if (($record['status'] ?? '') !== 'ok') {
                $lines[] = json_encode(['status' => 'indisponivel', 'line' => $record['line'] ?? null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }
            $lines[] = json_encode($record['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return implode("\n", $lines) . "\n";
    }

    public static function readRecords(string $date): array
    {
        if (!self::isValidDate($date)) throw new RuntimeException('Data inválida.');
        $path = self::pathForDate($date);
        if (!is_file($path)) return [];
        if (is_link($path)) throw new RuntimeException('Arquivo de auditoria inválido.');
        $size = filesize($path);
        if ($size === false) throw new RuntimeException('Não foi possível verificar o arquivo.');
        if ($size > self::MAX_FILE_BYTES) throw new RuntimeException('Arquivo excede o limite de consulta.');
        $handle = fopen($path, 'rb');
        if (!$handle) throw new RuntimeException('Falha na leitura.');
        $records = [];
        try {
            if (!flock($handle, LOCK_SH)) throw new RuntimeException('Arquivo ocupado.');
            $lineNumber = 0;
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $line = trim($line);
                if ($line === '') continue;
                try {
                    $decoded = json_decode(EncryptedBackup::decrypt($line, 'audit'), true, 512, JSON_THROW_ON_ERROR);
                    if (!is_array($decoded)) throw new RuntimeException('Formato inválido.');
                    $records[] = [
                        'status' => 'ok',
                        'line' => $lineNumber,
                        'data' => AuditRedactor::clean($decoded),
                    ];
                } catch (Throwable) {
                    $records[] = ['status' => 'error', 'line' => $lineNumber];
                }
            }
            if (!feof($handle)) throw new RuntimeException('Falha durante a leitura.');
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $records;
    }

    public static function availableFiles(): array
    {
        $directory = self::directory();
        if (!is_dir($directory)) return [];
        if (!is_readable($directory)) throw new RuntimeException('Diretório de auditoria indisponível.');

        $files = [];
        foreach (new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isLink() || !$entry->isFile()) continue;
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})\.txt\.enc$/D', $entry->getFilename(), $matches)) continue;
            if (!self::isValidDate($matches[1])) continue;
            $files[] = [
                'date' => $matches[1],
                'size' => $entry->getSize(),
                'modified_at' => gmdate('Y-m-d H:i:s', $entry->getMTime()) . ' UTC',
            ];
        }
        usort($files, static fn (array $left, array $right): int => strcmp($right['date'], $left['date']));

        return $files;
    }

    public static function isValidDate(string $date): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return false;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $parsed->format('Y-m-d') === $date;
    }

    private static function pathForDate(string $date): string
    {
        return self::directory() . DIRECTORY_SEPARATOR . $date . '.txt.enc';
    }
}
