<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$adminName = trim((string) config_value('ADMIN_NAME', 'Administrador'));
$adminEmail = trim((string) config_value('ADMIN_EMAIL', 'admin@empresa.com'));
$adminPassword = (string) config_value('ADMIN_PASSWORD', '');

if ($adminEmail === '') {
    fwrite(STDERR, 'Defina ADMIN_EMAIL em config/local.php ou nas variaveis de ambiente.' . PHP_EOL);
    exit(1);
}

if ($adminPassword === '') {
    $adminPassword = bin2hex(random_bytes(12));
    echo 'ADMIN_PASSWORD não definido; senha temporária gerada para o primeiro acesso.' . PHP_EOL;
}

$stmt = db()->prepare(
    'INSERT INTO users (name, email, password_hash, is_admin, is_active)
     VALUES (:name, :email, :password_hash, 1, 1)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), is_admin = 1, is_active = 1'
);

$stmt->execute([
    'name' => $adminName !== '' ? $adminName : 'Administrador',
    'email' => $adminEmail,
    'password_hash' => password_hash($adminPassword, PASSWORD_DEFAULT),
]);

echo 'Usuário administrador pronto: ' . $adminEmail . PHP_EOL;
echo 'Senha inicial: ' . $adminPassword . PHP_EOL;
echo 'Troque esta senha depois do primeiro acesso.' . PHP_EOL;
