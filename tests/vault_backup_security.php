<?php
declare(strict_types=1);
if (!defined('APP_ENV') || APP_ENV !== 'testing') exit(1);
$data = ['notes' => 'PRIVATE-NOTE-TEST', 'custom_fields' => '{"wifi":"PRIVATE-FIELD-TEST"}'];
$cipher = VaultCredential::encryptMetadata($data);
check('Cofre: metadados criptografados', !str_contains(serialize($cipher), 'PRIVATE-'));
check('Cofre: metadados recuperados', VaultCredential::decryptMetadata($cipher) === $data);
$sql = DatabaseMaintenance::dumpSql(false);
$encrypted = EncryptedBackup::encrypt($sql, 'sql');
check('Backup: SQL protegido', !str_contains($encrypted, 'CREATE TABLE'));
check('Backup: SQL recuperado integralmente', EncryptedBackup::decrypt($encrypted, 'sql') === $sql);
$tampered = substr($encrypted, 0, -8) . 'AAAAAAA=';
try { EncryptedBackup::decrypt($tampered, 'sql'); $rejected = false; } catch (Throwable $e) { $rejected = true; }
check('Backup: adulteracao recusada', $rejected);
$zip = DatabaseMaintenance::fullBackupZip();
check('Backup: ZIP recuperado integralmente', EncryptedBackup::decrypt(EncryptedBackup::encrypt($zip, 'zip'), 'zip') === $zip);
$path = tempnam(sys_get_temp_dir(), 'exe-restore-test-');
try {
    file_put_contents($path, $encrypted);
    $count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    DatabaseMaintenance::importSqlFile($path);
    check('Backup: restauracao SQL em banco isolado', (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === $count);
} finally { unlink($path); }
