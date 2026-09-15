<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/exe-crypto-' . bin2hex(random_bytes(8));
mkdir($temp . '/config', 0700, true);
define('BASE_PATH', $temp);
define('APP_KEY', 'fixture-legacy-key-not-production');
require $root . '/includes/VersionedCrypto.php';
require $root . '/includes/CredentialCrypto.php';
try {
    $legacy = CredentialCrypto::encrypt('old secret');
    $config = [];
    foreach (['credentials', 'totp', 'microsoft'] as $purpose) {
        $config[$purpose] = ['active' => 'new', 'keys' => ['new' => base64_encode(random_bytes(32))]];
    }
    file_put_contents($temp . '/config/crypto.local.php', '<?php return ' . var_export($config, true) . ';');
    foreach (array_keys($config) as $purpose) {
        $rotated = CredentialCrypto::rotate($legacy, $purpose);
        if (CredentialCrypto::decrypt($rotated, $purpose) !== 'old secret') throw new Exception('Legacy migration');
        $new = CredentialCrypto::encrypt('new secret', $purpose);
        if (!str_starts_with($new, 'enc:v2:' . $purpose . ':new:')) throw new Exception('New writes');
        if (CredentialCrypto::decrypt(CredentialCrypto::rotate($new, $purpose), $purpose) !== 'new secret') throw new Exception('Repeat migration');
    }
    foreach (['plain text', 'enc:v1:broken', 'enc:v2:totp:missing:AAAA'] as $bad) {
        try { CredentialCrypto::rotate($bad); }
        catch (RuntimeException $e) { continue; }
        throw new Exception('Unsafe migration accepted');
    }
    echo "OK: 3 purposes, legacy reads, versioned writes, repeat rotation, invalid records\n";
} finally {
    if (is_file($temp . '/config/crypto.local.php')) unlink($temp . '/config/crypto.local.php');
    rmdir($temp . '/config');
    rmdir($temp);
}
