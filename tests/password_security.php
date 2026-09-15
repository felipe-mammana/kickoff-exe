<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/PasswordSecurity.php';
require_once __DIR__ . '/../models/LoginAttempt.php';

function expectPassword(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "OK: $message\n";
}
foreach (['Abcdef1!', 'Long-Passphrase-123'] as $password) {
    expectPassword(PasswordSecurity::valid($password), 'valid password');
}
foreach (['abcdef1!', 'ABCDEF1!', 'Abcdefg!', 'Abcdef12', 'Ab1!', 'Abcdef1 ', str_repeat('A', 73) . 'a1!'] as $password) {
    expectPassword(!PasswordSecurity::valid($password), 'reject missing requirement or excessive length');
}
$legacy = password_hash('legacy password', PASSWORD_BCRYPT, ['cost' => 4]);
expectPassword(password_verify('legacy password', $legacy), 'legacy login remains valid');
expectPassword(password_needs_rehash($legacy, PasswordSecurity::algorithm(), PasswordSecurity::options()), 'legacy requires upgrade');
$hash = PasswordSecurity::hash('legacy password');
expectPassword(password_verify('legacy password', $hash), 'upgrade preserves old password without new-policy enforcement');
expectPassword(!password_needs_rehash($hash, PasswordSecurity::algorithm(), PasswordSecurity::options()), 'new hash stable');
expectPassword(!password_verify('wrong', $hash), 'wrong password rejected');
foreach ([4 => 0, 5 => 30, 6 => 60, 7 => 120, 8 => 240, 9 => 480, 10 => 900, 1000 => 900] as $count => $seconds) {
    expectPassword(LoginAttempt::delay($count) === $seconds, 'progressive delay ' . $count);
}
expectPassword(LoginAttempt::delay(29, 30) === 0 && LoginAttempt::delay(30, 30) === 30, 'origin threshold');
