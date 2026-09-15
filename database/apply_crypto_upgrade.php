<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
$args = array_slice($argv, 1);
if (array_diff($args, ['--discard-invalid-test-credentials'])) {
    fwrite(STDERR, "Argumento invalido.\n"); exit(1);
}
$discard = in_array('--discard-invalid-test-credentials', $args, true);
$lock = fopen(STORAGE_PATH . '/app-maintenance.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Existem requisicoes em andamento. Tente novamente.\n"); exit(1);
}
try {
    foreach (db()->query('SHOW TABLE STATUS')->fetchAll() as $table) {
        if (strtolower((string) $table['Engine']) !== 'innodb') throw new RuntimeException('Non transactional table');
    }
    // Backup precedes activation, never prints plaintext or keys.
    $dir = STORAGE_PATH . '/crypto-backups';
    if (!is_dir($dir)) mkdir($dir, 0700, true);
    $path = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
    $dump = DatabaseMaintenance::dumpSql(false);
    $iv = random_bytes(12);
    if (APP_KEY === '') throw new RuntimeException('Missing legacy key');
    $cipher = openssl_encrypt($dump, 'aes-256-gcm', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA,
        $iv, $tag, 'exe:database-backup:v1', 16);
    if ($cipher === false) throw new RuntimeException('Backup encryption failed');
    $backup = json_encode(['format' => 'exe:database-backup:v1', 'iv' => base64_encode($iv),
        'tag' => base64_encode($tag), 'ciphertext' => base64_encode($cipher)], JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $backup, LOCK_EX) !== strlen($backup)) throw new RuntimeException('Backup write failed');
    chmod($path, 0600);
    $verify = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $plain = openssl_decrypt(base64_decode($verify['ciphertext']), 'aes-256-gcm', hash('sha256', APP_KEY, true),
        OPENSSL_RAW_DATA, base64_decode($verify['iv']), base64_decode($verify['tag']), $verify['format']);
    if (!is_string($plain) || !hash_equals(hash('sha256', $dump), hash('sha256', $plain))) throw new RuntimeException('Backup verification failed');
    unset($dump, $plain, $backup, $cipher);
    echo "Backup criptografado e leitura conferida: " . basename($path) . PHP_EOL;
    if (!is_file(BASE_PATH . '/config/crypto.local.php')) require __DIR__ . '/prepare_crypto_keys.php';
    $counts = CryptoRotation::run(db(), false, $discard);
    echo "Simulacao validada.\n";
    CryptoRotation::run(db(), true, $discard);
    CryptoRotation::run(db(), false);
    foreach ($counts as $target => $count) echo $target . ': ' . $count . PHP_EOL;
    echo "Migracao concluida e leitura pos-migracao validada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e) === RuntimeException::class ? $e->getMessage() . PHP_EOL : "Falha de infraestrutura; valores omitidos.\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
