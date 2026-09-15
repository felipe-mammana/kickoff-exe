<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$apply = in_array('--apply', $argv, true);
$lock = fopen(STORAGE_PATH . '/app-maintenance.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit("Aguarde as requisicoes ativas.\n");
try {
    if ($apply) {
        $directory = STORAGE_PATH . '/crypto-backups';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Falha no diretorio de backup.');
        $sql = DatabaseMaintenance::dumpSql(false);
        $backup = EncryptedBackup::encrypt($sql, 'sql');
        $path = $directory . '/audit-redaction-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.exe-sql';
        if (file_put_contents($path, $backup, LOCK_EX) !== strlen($backup)) throw new RuntimeException('Falha ao salvar backup.');
        if (!hash_equals($sql, EncryptedBackup::decrypt((string) file_get_contents($path), 'sql'))) throw new RuntimeException('Backup nao conferiu.');
    }
    db()->beginTransaction();
    $rows = db()->query('SELECT id, old_data, new_data FROM audit_logs FOR UPDATE')->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        $clean = ['id' => $row['id'], 'old_data' => AuditRedactor::historical($row['old_data']), 'new_data' => AuditRedactor::historical($row['new_data'])];
        if ($clean['old_data'] === $row['old_data'] && $clean['new_data'] === $row['new_data']) continue;
        $count++;
        if ($apply) {
            $stmt = db()->prepare('UPDATE audit_logs SET old_data = :old_data, new_data = :new_data WHERE id = :id');
            $stmt->execute($clean);
        }
    }
    $apply ? db()->commit() : db()->rollBack();
    if ($apply) AuditLog::record(['action_type' => 'audit_history_redacted', 'description' => 'Payloads historicos saneados com backup criptografado previo.', 'new_data' => ['records' => $count], 'required' => true]);
    echo ($apply ? 'Atualizados: ' : 'A atualizar: ') . $count . " registros; nenhum valor exibido.\n";
} finally {
    if (db()->inTransaction()) db()->rollBack();
    flock($lock, LOCK_UN);
    fclose($lock);
}
