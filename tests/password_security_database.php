<?php
declare(strict_types=1);
// Included only inside the isolated integration database.
if (!defined('APP_ENV') || APP_ENV !== 'testing') exit(1);
$email = 'limit-fixture@example.test';
$ip = '192.0.2.99';
for ($i = 0; $i < 5; $i++) LoginAttempt::recordFailure($email, $ip);
check('Login: conta bloqueada apos cinco falhas', LoginAttempt::isBlocked($email, $ip));
check('Login: trocar IP nao contorna limite da conta', LoginAttempt::isBlocked($email, '192.0.2.100'));
$before = LoginAttempt::remainingSeconds($email, $ip);
LoginAttempt::isBlocked($email, $ip);
check('Login: consulta bloqueada nao renova prazo', LoginAttempt::remainingSeconds($email, $ip) <= $before);
db()->exec("UPDATE login_attempts SET attempted_at = DATE_SUB(NOW(), INTERVAL 31 SECOND) WHERE email = 'limit-fixture@example.test'");
check('Login: bloqueio expira sem intervencao', !LoginAttempt::isBlocked($email, $ip));
LoginAttempt::recordFailure($email, $ip);
check('Login: reincidencia aumenta prazo', LoginAttempt::remainingSeconds($email, $ip) > 30);
for ($i = 0; $i < 30; $i++) LoginAttempt::recordFailure('spray-' . $i . '@example.test', '192.0.2.101');
check('Login: origem bloqueada em contas diferentes', LoginAttempt::isBlocked('another@example.test', '192.0.2.101'));
db()->exec("DELETE FROM login_attempts WHERE ip_address IN ('192.0.2.99','192.0.2.101')");
$fixture = User::find($adminId);
for ($i = 0; $i < 5; $i++) PasswordSecurity::confirm($fixture, 'wrong-test-password');
check('Reautenticacao: senha correta recusada durante bloqueio', !PasswordSecurity::confirm($fixture, 'SenhaForte123!'));
$stmt = db()->prepare('DELETE FROM login_attempts WHERE email = :email');
$stmt->execute(['email' => 'reauth:' . $adminId]);
check('Reautenticacao: senha correta aceita apos desbloqueio', PasswordSecurity::confirm($fixture, 'SenhaForte123!'));
