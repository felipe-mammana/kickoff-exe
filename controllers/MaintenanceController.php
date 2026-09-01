<?php

declare(strict_types=1);

class MaintenanceController
{
    public static function exportCleanDatabase(): void
    {
        require_admin();

        self::download(
            'application/sql; charset=utf-8',
            'exe-banco-limpo-' . date('Ymd-His') . '.sql',
            DatabaseMaintenance::dumpSql(true)
        );
    }

    public static function exportFullBackup(): void
    {
        require_admin();

        self::download(
            'application/zip',
            'exe-backup-completo-' . date('Ymd-His') . '.zip',
            DatabaseMaintenance::fullBackupZip()
        );
    }

    public static function importDatabase(): void
    {
        require_admin();
        verify_csrf();

        if (empty($_FILES['backup_sql']) || !is_array($_FILES['backup_sql'])) {
            flash('danger', 'Selecione um arquivo SQL para importar.');
            redirect('/?route=settings.index');
        }

        $file = $_FILES['backup_sql'];
        $originalName = safe_original_filename((string) ($file['name'] ?? 'backup.sql'));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $extension !== 'sql') {
            flash('danger', 'Envie um arquivo .sql válido.');
            redirect('/?route=settings.index');
        }

        try {
            $executed = DatabaseMaintenance::importSqlFile((string) ($file['tmp_name'] ?? ''));
        } catch (Throwable $exception) {
            flash('danger', 'Não foi possível importar o banco: ' . $exception->getMessage());
            redirect('/?route=settings.index');
        }

        AuditLog::record([
            'action_type' => 'database_imported',
            'affected_table' => 'database',
            'description' => 'Backup SQL importado pelo painel de manutenção.',
            'new_data' => [
                'Arquivo' => $originalName,
                'Comandos executados' => $executed,
            ],
        ]);

        flash('success', 'Backup importado com sucesso. Comandos executados: ' . $executed . '.');
        redirect('/?route=settings.index');
    }

    public static function cleanupOrphans(): void
    {
        require_admin();
        verify_csrf();

        $result = DatabaseMaintenance::cleanupOrphanFiles();

        AuditLog::record([
            'action_type' => 'orphan_files_cleaned',
            'affected_table' => 'storage',
            'description' => 'Arquivos órfãos removidos pela manutenção.',
            'old_data' => [
                'Arquivos removidos' => $result['deleted'],
                'Espaço liberado' => format_file_size((int) $result['bytes']),
            ],
        ]);

        flash('success', $result['deleted'] . ' arquivo(s) órfão(s) removido(s). Espaço liberado: ' . format_file_size((int) $result['bytes']) . '.');
        redirect('/?route=settings.index');
    }

    private static function download(string $contentType, string $filename, string $contents): void
    {
        AuditLog::record([
            'action_type' => str_ends_with($filename, '.zip') ? 'full_backup_exported' : 'clean_database_exported',
            'affected_table' => 'database',
            'description' => str_ends_with($filename, '.zip') ? 'Backup completo exportado.' : 'Banco limpo exportado.',
            'new_data' => ['Arquivo' => $filename],
        ]);

        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . strlen($contents));
        header('Content-Disposition: attachment; filename="' . safe_download_filename($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        echo $contents;
        exit;
    }
}
