<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$apply = in_array('--apply', $argv, true);
$lock = fopen(STORAGE_PATH . '/app-maintenance.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Aguarde as requisicoes ativas.');
try {
    if ($apply) {
        $path = STORAGE_PATH . '/crypto-backups/metadata-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.exe-sql';
        $sql = DatabaseMaintenance::dumpSql(false);
        $backup = EncryptedBackup::encrypt($sql, 'sql');
        if (!hash_equals($sql, EncryptedBackup::decrypt($backup, 'sql'))) throw new RuntimeException('Falha no backup.');
        if (file_put_contents($path, $backup, LOCK_EX) !== strlen($backup)) throw new RuntimeException('Falha ao salvar backup.');
    }
    db()->beginTransaction();
    $rows = db()->query('SELECT id, notes, custom_fields FROM vault_credentials FOR UPDATE')->fetchAll();
    $count = 0;
    foreach ($rows as $row) {
        $encrypted = VaultCredential::encryptMetadata($row);
        $decoded = VaultCredential::decryptMetadata($encrypted);
        foreach (['notes', 'custom_fields'] as $field) {
            if (str_starts_with((string) $row[$field], 'enc:')) continue;
            if ($decoded[$field] !== $row[$field]) throw new RuntimeException('Falha ao validar registro.');
        }
        if ($apply) {
            $stmt = db()->prepare('UPDATE vault_credentials SET notes = :notes, custom_fields = :custom_fields WHERE id = :id');
            $stmt->execute($encrypted);
        }
        $count++;
    }
    $apply ? db()->commit() : db()->rollBack();
    echo ($apply ? 'Migrados: ' : 'Validados: ') . $count . " registros.\n";
} finally {
    if (db()->inTransaction()) db()->rollBack();
    flock($lock, LOCK_UN);
    fclose($lock);
}
