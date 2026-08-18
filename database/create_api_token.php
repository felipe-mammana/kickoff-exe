<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$email = trim((string) ($argv[1] ?? config_value('ADMIN_EMAIL', '')));
$name = trim((string) ($argv[2] ?? 'API token'));
$days = isset($argv[3]) ? max(1, (int) $argv[3]) : 0;

if ($email === '') {
    fwrite(STDERR, 'Uso: php database/create_api_token.php usuario@email.com "Nome do token" [dias_validade]' . PHP_EOL);
    exit(1);
}

$user = User::findByEmail($email);
if (!$user) {
    fwrite(STDERR, "Usuario nao encontrado: {$email}" . PHP_EOL);
    exit(1);
}

$plainToken = ApiToken::generatePlainToken();
$expiresAt = $days > 0 ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;
$tokenId = ApiToken::create((int) $user['id'], $name !== '' ? $name : 'API token', $plainToken, $expiresAt);

echo 'Token de API criado.' . PHP_EOL;
echo 'ID: ' . $tokenId . PHP_EOL;
echo 'Usuario: ' . $email . PHP_EOL;
echo 'Nome: ' . ($name !== '' ? $name : 'API token') . PHP_EOL;
echo 'Expira em: ' . ($expiresAt ?: 'Nunca') . PHP_EOL;
echo 'Token: ' . $plainToken . PHP_EOL;
echo 'Guarde este token agora. Ele nao sera exibido novamente.' . PHP_EOL;
