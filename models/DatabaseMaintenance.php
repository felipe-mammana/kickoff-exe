<?php

declare(strict_types=1);

class DatabaseMaintenance
{
    private const CLEAN_SKIP_TABLES = ['audit_logs', 'api_rate_limits', 'api_tokens', 'login_attempts'];

    public static function status(): array
    {
        return [
            'database_name' => DB_NAME,
            'tables' => self::tableStatuses(),
            'database_size' => self::databaseSize(),
            'machine_photos' => self::directoryStatus(self::machinePhotoDirectories()),
            'company_attachments' => self::directoryStatus([self::companyAttachmentDirectory()]),
            'orphans' => self::orphanSummary(),
        ];
    }

    public static function dumpSql(bool $clean = false): string
    {
        $pdo = db();
        $lines = [
            '-- Backup gerado pelo sistema ' . APP_NAME,
            '-- Data: ' . date('Y-m-d H:i:s'),
            '-- Tipo: ' . ($clean ? 'banco limpo' : 'banco completo'),
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET NAMES utf8mb4;',
            '',
        ];

        foreach (self::tableNames() as $table) {
            if ($clean && in_array($table, self::CLEAN_SKIP_TABLES, true)) {
                continue;
            }

            $quotedTable = self::quoteIdentifier($table);
            $create = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch();
            $createSql = (string) ($create['Create Table'] ?? $create[1] ?? '');

            $lines[] = 'DROP TABLE IF EXISTS ' . $quotedTable . ';';
            $lines[] = $createSql . ';';
            $lines[] = '';

            $stmt = $pdo->query('SELECT * FROM ' . $quotedTable);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($clean && $table === 'users') {
                    $row['active_session_token'] = null;
                    $row['active_session_started_at'] = null;
                    $row['active_session_ip'] = null;
                    $row['active_session_user_agent'] = null;
                }

                $columns = array_map([self::class, 'quoteIdentifier'], array_keys($row));
                $values = array_map([self::class, 'quoteValue'], array_values($row));
                $lines[] = 'INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ');';
            }

            $lines[] = '';
        }

        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

        return implode("\n", $lines) . "\n";
    }

    public static function fullBackupZip(): string
    {
        $zip = new SimpleZipWriter();
        $zip->addFile('database-full.sql', self::dumpSql(false));
        $zip->addFile('manifest.json', json_encode([
            'app' => APP_NAME,
            'generated_at' => date('c'),
            'database' => DB_NAME,
            'includes' => ['database-full.sql', 'storage/machine_photos', 'storage/company_attachments'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

        foreach (self::backupDirectories() as $prefix => $directory) {
            self::addDirectoryToZip($zip, $prefix, $directory);
        }

        return $zip->output();
    }

    public static function importSqlFile(string $path): int
    {
        if (!is_file($path) || filesize($path) === false || filesize($path) > 50 * 1024 * 1024) {
            throw new RuntimeException('Arquivo de importação inválido ou maior que 50 MB.');
        }

        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            throw new RuntimeException('Arquivo SQL vazio ou ilegível.');
        }

        $statements = self::splitSqlStatements($sql);
        $executed = 0;
        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '') {
                    continue;
                }

                $pdo->exec($statement);
                $executed++;
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        return $executed;
    }

    public static function orphanSummary(): array
    {
        $orphans = self::orphanFiles();

        return [
            'machine_photos' => count($orphans['machine_photos']),
            'company_attachments' => count($orphans['company_attachments']),
            'total' => count($orphans['machine_photos']) + count($orphans['company_attachments']),
            'bytes' => array_sum(array_map(static fn (array $file): int => $file['size'], $orphans['machine_photos']))
                + array_sum(array_map(static fn (array $file): int => $file['size'], $orphans['company_attachments'])),
        ];
    }

    public static function cleanupOrphanFiles(): array
    {
        $orphans = self::orphanFiles();
        $deleted = 0;
        $bytes = 0;

        foreach (array_merge($orphans['machine_photos'], $orphans['company_attachments']) as $file) {
            $path = (string) $file['path'];
            if (self::pathIsInAllowedStorage($path) && is_file($path) && is_writable($path) && @unlink($path)) {
                $deleted++;
                $bytes += (int) $file['size'];
            }
        }

        return ['deleted' => $deleted, 'bytes' => $bytes];
    }

    private static function tableStatuses(): array
    {
        $statuses = [];
        $stmt = db()->query(
            'SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME'
        );

        foreach ($stmt->fetchAll() as $row) {
            $statuses[] = [
                'name' => (string) $row['TABLE_NAME'],
                'rows' => (int) ($row['TABLE_ROWS'] ?? 0),
                'bytes' => (int) ($row['DATA_LENGTH'] ?? 0) + (int) ($row['INDEX_LENGTH'] ?? 0),
            ];
        }

        return $statuses;
    }

    private static function databaseSize(): int
    {
        $stmt = db()->query(
            'SELECT COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0)
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()'
        );

        return (int) $stmt->fetchColumn();
    }

    private static function tableNames(): array
    {
        return array_values(array_filter(array_map(
            static fn ($value): string => (string) $value,
            db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
        ), static fn (string $table): bool => preg_match('/^[A-Za-z0-9_]+$/', $table) === 1));
    }

    private static function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $identifier) !== 1) {
            throw new InvalidArgumentException('Identificador inválido.');
        }

        return '`' . $identifier . '`';
    }

    private static function quoteValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return db()->quote((string) $value);
    }

    private static function directoryStatus(array $directories): array
    {
        $files = 0;
        $bytes = 0;

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (self::filesInDirectory($directory) as $path) {
                $files++;
                $bytes += filesize($path) ?: 0;
            }
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    private static function orphanFiles(): array
    {
        $photoNames = self::referencedNames('machine_photos', 'file_name');
        $attachmentNames = self::referencedNames('company_attachments', 'disk_name');

        return [
            'machine_photos' => self::unreferencedFiles(self::machinePhotoDirectories(), $photoNames),
            'company_attachments' => self::unreferencedFiles([self::companyAttachmentDirectory()], $attachmentNames),
        ];
    }

    private static function referencedNames(string $table, string $column): array
    {
        $stmt = db()->query('SELECT ' . self::quoteIdentifier($column) . ' FROM ' . self::quoteIdentifier($table));
        $names = [];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $name = basename((string) $name);
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return $names;
    }

    private static function unreferencedFiles(array $directories, array $referencedNames): array
    {
        $files = [];
        foreach ($directories as $directory) {
            foreach (self::filesInDirectory($directory) as $path) {
                $name = basename($path);
                if (!isset($referencedNames[$name])) {
                    $files[] = [
                        'path' => $path,
                        'name' => $name,
                        'size' => filesize($path) ?: 0,
                    ];
                }
            }
        }

        return $files;
    }

    private static function filesInDirectory(string $directory): array
    {
        $root = realpath($directory);
        if ($root === false || !is_dir($root)) {
            return [];
        }

        $files = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        return $files;
    }

    private static function addDirectoryToZip(SimpleZipWriter $zip, string $prefix, string $directory): void
    {
        foreach (self::filesInDirectory($directory) as $path) {
            $zip->addFile($prefix . '/' . basename($path), file_get_contents($path) ?: '');
        }
    }

    private static function backupDirectories(): array
    {
        $directories = [
            'storage/machine_photos' => UPLOAD_PATH,
            'storage/company_attachments' => self::companyAttachmentDirectory(),
        ];

        if (defined('LEGACY_UPLOAD_PATH') && is_dir(LEGACY_UPLOAD_PATH)) {
            $directories['public/uploads'] = LEGACY_UPLOAD_PATH;
        }

        return $directories;
    }

    private static function machinePhotoDirectories(): array
    {
        return [UPLOAD_PATH];
    }

    private static function companyAttachmentDirectory(): string
    {
        return STORAGE_PATH . DIRECTORY_SEPARATOR . 'company_attachments';
    }

    private static function pathIsInAllowedStorage(string $path): bool
    {
        $realPath = realpath($path);
        if ($realPath === false) {
            return false;
        }

        foreach (array_merge(self::machinePhotoDirectories(), [self::companyAttachmentDirectory()]) as $directory) {
            $root = realpath($directory);
            if ($root !== false && str_starts_with($realPath, $root . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            if ($quote === null && $char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if (($char === "'" || $char === '"' || $char === '`') && ($i === 0 || $sql[$i - 1] !== '\\')) {
                $quote = $quote === $char ? null : ($quote ?? $char);
            }

            if ($char === ';' && $quote === null) {
                $statements[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }
}
