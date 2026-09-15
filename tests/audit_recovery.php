<?php
declare(strict_types=1);
if (!defined('APP_ENV') || APP_ENV !== 'testing') exit(1);
$redacted = AuditRedactor::clean(['id' => 8, 'nested' => ['secret_value' => 'PRIVATE', 'notes' => 'PRIVATE', 'custom_fields' => ['wifi' => 'PRIVATE']]]);
check('Auditoria: campos sensiveis aninhados mascarados', !str_contains(serialize($redacted), 'PRIVATE') && $redacted['id'] === 8);
check('Auditoria: payload legado malformado protegido', AuditRedactor::historical('PRIVATE') === '"[protegido]"');
check('Cofre: prazo de vinte minutos', VaultSession::IDLE_SECONDS === 1200);
ProtectedAuditFiles::append(['action_type' => 'test_protected_file', 'secret_value' => 'HIDDEN-TEST-SECRET']);
$protectedText = ProtectedAuditFiles::read(gmdate('Y-m-d'));
check('Auditoria: arquivo protegido recupera registro sem segredo', str_contains($protectedText, 'test_protected_file') && !str_contains($protectedText, 'HIDDEN-TEST-SECRET'));
$disk = file_get_contents(ProtectedAuditFiles::directory() . '/' . gmdate('Y-m-d') . '.txt.enc');
check('Auditoria: arquivo em disco nao contem texto do evento', !str_contains($disk, 'test_protected_file'));
$fixtureName = 'recovery-test-' . bin2hex(random_bytes(5)) . '.txt';
$attachmentDirectory = STORAGE_PATH . '/company_attachments';
if (!is_dir($attachmentDirectory)) mkdir($attachmentDirectory, 0700, true);
$fixturePath = $attachmentDirectory . '/' . $fixtureName;
$destination = sys_get_temp_dir() . '/exe-recovered-' . bin2hex(random_bytes(8));
$hashes = [];
try {
    file_put_contents($fixturePath, 'RECOVERY-FIXTURE-' . bin2hex(random_bytes(16)));
    $expected = hash_file('sha256', $fixturePath);
    $hashes = BackupRecovery::stage(EncryptedBackup::encrypt(DatabaseMaintenance::fullBackupZip(), 'zip'), $destination);
    check('Backup: anexo extraido confere com original', ($hashes['storage/company_attachments/' . $fixtureName] ?? '') === $expected);
    check('Backup: SQL e manifesto recuperados', isset($hashes['database-full.sql'], $hashes['manifest.json']));
    try { BackupRecovery::stage(EncryptedBackup::encrypt(DatabaseMaintenance::fullBackupZip(), 'zip'), $destination); $rejected = false; }
    catch (RuntimeException $e) { $rejected = true; }
    check('Backup: nao sobrescreve destino existente', $rejected);
    $attack = new SimpleZipWriter();
    $attack->addFile('../escape.txt', 'bad');
    try { BackupRecovery::stage(EncryptedBackup::encrypt($attack->output(), 'zip'), $destination . '-unsafe'); $rejected = false; }
    catch (RuntimeException $e) { $rejected = true; }
    check('Backup: path traversal recusado antes da extracao', $rejected && !file_exists($destination . '-unsafe'));
} finally {
    unlink($fixturePath);
    // Remove only the newly generated, enumerated recovery tree.
    if (is_dir($destination)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) { $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname()); }
        rmdir($destination);
    }
}
