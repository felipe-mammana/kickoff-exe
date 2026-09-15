<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
$email = 'cofre@exe.com.br';
if (User::findByEmail($email)) {
    fwrite(STDERR, "Conta existente; nenhuma senha ou permissao alterada.\n");
    exit(1);
}
$password = trim((string) fgets(STDIN));
if (strlen($password) < 7) { exit(1); }
$id = User::create(['name' => 'Cofre - Testes', 'email' => $email, 'password' => $password, 'role' => 'viewer', 'is_active' => 1]);
AppSetting::set('vault_exclusive_user_id', (string) $id);
echo "Conta exclusiva criada com perfil viewer.\n";
