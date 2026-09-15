<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
define('APP_KEY', 'test-only-key');
require BASE_PATH . '/includes/VersionedCrypto.php';
require BASE_PATH . '/includes/CredentialCrypto.php';
$keys = ['old' => base64_encode(random_bytes(32)), 'new' => base64_encode(random_bytes(32))];
$cipher = VersionedCrypto::encrypt('test secret', 'credentials', 'old', $keys);
if (VersionedCrypto::decrypt($cipher, 'credentials', $keys) !== 'test secret') throw new Exception('Roundtrip');
if (VersionedCrypto::encrypt('test secret', 'credentials', 'old', $keys) === $cipher) throw new Exception('Nonce reuse');
$parts = explode(':', $cipher, 5);
$raw = base64_decode($parts[4]);
$raw[28] = chr(ord($raw[28]) ^ 1);
$parts[4] = base64_encode($raw);
foreach ([
    [implode(':', $parts), 'credentials', $keys],
    [$cipher, 'totp', $keys],
    [$cipher, 'credentials', ['new' => $keys['new']]],
] as [$input, $purpose, $ring]) {
    try { VersionedCrypto::decrypt($input, $purpose, $ring); }
    catch (RuntimeException $e) { continue; }
    throw new Exception('Invalid ciphertext accepted');
}
$rotated = VersionedCrypto::encrypt(VersionedCrypto::decrypt($cipher, 'credentials', $keys), 'credentials', 'new', $keys);
if (VersionedCrypto::decrypt($rotated, 'credentials', ['new' => $keys['new']]) !== 'test secret') throw new Exception('Rotation');
$legacy = CredentialCrypto::encrypt('legacy');
if (CredentialCrypto::decrypt($legacy) !== 'legacy') throw new Exception('Legacy');
echo "OK: roundtrip, nonce, tamper, purpose, missing key, rotation, legacy\n";
