<?php

declare(strict_types=1);

// Included by run.php after it creates the isolated test database.
if (PHP_SAPI !== 'cli' || !defined('APP_ENV') || APP_ENV !== 'testing') {
    exit(1);
}

class AccountSecurityHttpClient
{
    private $curl;
    private string $csrf = '';
    public array $sessionIds = [];

    public function __construct(private string $baseUrl)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 20]);
    }

    public function request(string $route, ?array $data = null): array
    {
        if ($data !== null && $this->csrf === '') $this->request('login');
        curl_setopt($this->curl, CURLOPT_URL, $this->baseUrl . '/?route=' . $route);
        if ($data !== null) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($data + ['csrf_token' => $this->csrf]));
        } else {
            curl_setopt($this->curl, CURLOPT_HTTPGET, true);
        }
        $response = curl_exec($this->curl);
        if ($response === false) throw new RuntimeException(curl_error($this->curl));
        if (curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE) >= 500) {
            throw new RuntimeException('HTTP 500 in test route ' . $route);
        }
        $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        if (preg_match('/name="csrf_token" value="([^"]+)"/', $body, $match)) $this->csrf = html_entity_decode($match[1]);
        foreach (curl_getinfo($this->curl, CURLINFO_COOKIELIST) as $cookie) {
            $parts = explode("\t", $cookie);
            if (($parts[5] ?? '') === 'exe_session') $this->sessionIds[] = $parts[6];
        }
        return ['status' => curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE), 'headers' => $headers, 'body' => $body];
    }

    public function login(string $email, string $password): array
    {
        $this->request('login');
        return $this->request('login', ['email' => $email, 'password' => $password]);
    }

    public function session(): array
    {
        $id = end($this->sessionIds);
        if (!$id || !preg_match('/^[A-Za-z0-9,-]+$/D', $id)) throw new RuntimeException('Invalid test session');
        $path = STORAGE_PATH . '/sessions/sess_' . $id;
        return [$path, unserialize(file_get_contents($path), ['allowed_classes' => false])];
    }
}

function securityTestPort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException($error);
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    return (int) substr(strrchr($address, ':'), 1);
}

function securityTestWait(int $port): void
{
    for ($i = 0; $i < 60; $i++) {
        $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.1);
        if ($socket) { fclose($socket); return; }
        usleep(100000);
    }
    throw new RuntimeException('Test server did not start');
}

function securityTestMail(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $mail = $lines ? json_decode(end($lines), true, 512, JSON_THROW_ON_ERROR) : [];
    preg_match('/\b[1-9][0-9]{5}\b/', (string) ($mail['body'] ?? ''), $match);
    return $mail + ['code' => $match[0] ?? '', 'count' => count($lines)];
}

function securityTestExpire(string $purpose, int $userId): void
{
    db()->prepare('UPDATE security_rate_limits SET expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE scope_key = :key')
        ->execute(['key' => hash('sha256', $purpose . ':' . $userId)]);
}

function approveAccountChange(AccountSecurityHttpClient $client, string $mailFile, int $userId): array
{
    SecurityRateLimit::clear('account-change-send', $userId);
    SecurityRateLimit::clear('account-change-send-window', $userId);
    $client->request('account.challenge');
    $before = securityTestMail($mailFile)['count'];
    $response = $client->request('account.challenge', ['operation' => 'send']);
    $mail = securityTestMail($mailFile);
    if ($mail['count'] === $before) return $response;
    check('Confirmacao: codigo enviado ao email atual', str_contains($mail['recipient'] ?? '', User::find($userId)['email']));
    return $client->request('account.challenge', ['operation' => 'verify', 'method' => 'email', 'code' => $mail['code']]);
}

$processes = [];
$clients = [];
$mailFile = tempnam(STORAGE_PATH . '/tmp', 'security-mail-');
$serverLog = tempnam(STORAGE_PATH . '/tmp', 'security-server-');
$failureFile = $mailFile . '.fail';
$securityUserId = User::create(['name' => 'Security Test', 'email' => 'security@example.test', 'password' => 'Security-Test-Password-123', 'role' => 'viewer', 'is_active' => 1]);
$securityAdminId = User::create(['name' => 'Security Admin', 'email' => 'security-admin@example.test', 'password' => 'Security-Admin-Password-123', 'role' => 'admin', 'is_active' => 1]);
$securityPassword = 'Security-Test-Password-123';
AppSetting::set('vault_exclusive_user_id', (string) $securityUserId);
$appPort = securityTestPort();
$mailPort = securityTestPort();
$baseUrl = 'http://127.0.0.1:' . $appPort;
$initialFailures = $failed;
$securityTestCompleted = false;

try {
    $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']];
    $processes[] = proc_open([PHP_BINARY, __DIR__ . '/fixtures/smtp_server.php', (string) $mailPort, $mailFile, $failureFile], $descriptors, $pipes, BASE_PATH);
    foreach ($pipes as $pipe) fclose($pipe);
    securityTestWait($mailPort);
    $environment = array_merge(getenv(), [
        'APP_URL' => $baseUrl, 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
        'SESSION_ADMIN_PASSWORD_HASH' => PasswordSecurity::hash('Session-Admin-Test-123'),
        'SMTP_HOST' => '127.0.0.1', 'SMTP_PORT' => (string) $mailPort,
        'SMTP_USERNAME' => 'test@example.test', 'SMTP_PASSWORD' => 'test-only',
        'SMTP_ENCRYPTION' => 'none', 'MAIL_FROM' => 'no-reply@example.test',
    ]);
    $processes[] = proc_open([PHP_BINARY, '-d', 'session.serialize_handler=php_serialize', '-S', '127.0.0.1:' . $appPort, '-t', BASE_PATH . '/public'], $descriptors, $pipes, BASE_PATH, $environment);
    foreach ($pipes as $pipe) fclose($pipe);
    securityTestWait($appPort);
    $clients[] = $client = new AccountSecurityHttpClient($baseUrl);
    $legacyHash = password_hash($securityPassword, PASSWORD_BCRYPT, ['cost' => 4]);
    $stmt = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute(['hash' => $legacyHash, 'id' => $securityUserId]);
    check('HTTP: usuario entra normalmente', $client->login('security@example.test', $securityPassword)['status'] === 302);
    check('HTTP: login atualiza hash legado', !password_needs_rehash(User::find($securityUserId)['password_hash'], PasswordSecurity::algorithm(), PasswordSecurity::options()));
    $sessionsResponse = $client->request('sessions.index');
    check('Sessoes: usuario do cofre autorizado sem atalho de auditoria nao permitida', $sessionsResponse['status'] === 200 && !str_contains($sessionsResponse['body'], 'audit.index&amp;user_id=' . $securityUserId));
    check('Sessoes: resposta nao pode ser armazenada em cache', stripos($sessionsResponse['headers'], 'no-store') !== false);
    $terminationUser = User::create(['name' => 'Termination Test', 'email' => 'terminate@example.test', 'password' => 'Termination-Test-123', 'role' => 'viewer', 'is_active' => 1]);
    User::setActiveSession($terminationUser, bin2hex(random_bytes(32)));
    $client->request('sessions.terminate', ['user_id' => $terminationUser]);
    $client->request('sessions.terminate', ['confirm_termination' => '1', 'admin_password' => 'wrong']);
    check('Sessoes: senha administrativa incorreta nao encerra', !empty(User::find($terminationUser)['active_session_token']));
    $client->request('sessions.terminate', ['confirm_termination' => '1', 'admin_password' => 'Session-Admin-Test-123']);
    check('Sessoes: senha administrativa correta encerra', empty(User::find($terminationUser)['active_session_token']));
    User::setActiveSession($terminationUser, bin2hex(random_bytes(32)));
    $client->request('sessions.terminate', ['user_id' => $terminationUser]);
    User::setActiveSession($terminationUser, bin2hex(random_bytes(32)));
    $client->request('sessions.terminate', ['confirm_termination' => '1', 'admin_password' => 'Session-Admin-Test-123']);
    check('Sessoes: confirmacao antiga nao encerra novo login', !empty(User::find($terminationUser)['active_session_token']));
    $locked = $client->request('vault.index');
    check('Cofre: sessao inicia bloqueada sem cache', $locked['status'] === 423 && stripos($locked['headers'], 'no-store') !== false);
    check('Cofre: senha incorreta nao desbloqueia', $client->request('vault.index', ['vault_unlock' => '1', 'password' => 'wrong'])['status'] === 423);
    check('Cofre: senha correta desbloqueia', $client->request('vault.index', ['vault_unlock' => '1', 'password' => $securityPassword])['status'] === 302);
    check('Cofre: desbloqueado acessa listagem', $client->request('vault.index')['status'] === 200);

    $client->request('settings.security.update', ['vault_require_password_reveal' => '1', 'session_timeout_minutes' => '480']);
    $client->request('settings.security.update', ['session_timeout_minutes' => '60']);
    $user = User::find($securityUserId);
    check('Cofre: desativacao sem senha nao altera protecao nem expiracao', (int) $user['vault_require_password_reveal'] === 1 && (int) $user['session_timeout_minutes'] === 480);
    $client->request('settings.security.update', ['session_timeout_minutes' => '60', 'current_password' => 'wrong']);
    check('Cofre: senha incorreta nao desativa protecao', (int) User::find($securityUserId)['vault_require_password_reveal'] === 1);
    $client->request('settings.security.update', ['session_timeout_minutes' => '60', 'current_password' => $securityPassword]);
    check('Cofre: senha correta autoriza desativacao', (int) User::find($securityUserId)['vault_require_password_reveal'] === 0);
    $response = $client->request('settings.security.update', ['csrf_token' => 'invalid', 'vault_require_password_reveal' => '1']);
    check('Cofre: CSRF invalido continua recusado (HTTP ' . $response['status'] . ')', $response['status'] === 419 && (int) User::find($securityUserId)['vault_require_password_reveal'] === 0);

    $profile = ['name' => 'Security Updated', 'email' => 'security-new@example.test'];
    $client->request('settings.profile.update', $profile);
    $client->request('settings.profile.update', $profile + ['current_password' => 'wrong']);
    check('Email: senha ausente ou incorreta nao altera endereco nem envia codigo', User::find($securityUserId)['email'] === 'security@example.test' && securityTestMail($mailFile)['count'] === 0);
    $client->request('settings.profile.update', $profile + ['current_password' => $securityPassword]);
    approveAccountChange($client, $mailFile, $securityUserId);
    $mail = securityTestMail($mailFile);
    check('Email: codigo vai ao novo endereco e antigo permanece ativo', str_contains($mail['recipient'] ?? '', $profile['email']) && strlen($mail['code']) === 6 && User::find($securityUserId)['email'] === 'security@example.test');
    [$sessionPath, $sessionData] = $client->session();
    check('Email: codigo fica somente como hash na sessao', password_verify($mail['code'], $sessionData['pending_email_change']['code_hash']) && !str_contains(serialize($sessionData), $mail['code']));
    $client->request('settings.email.resend', []);
    check('Email: reenvio imediato bloqueado', securityTestMail($mailFile)['count'] === $mail['count']);
    $client->request('settings.email.confirm', ['email_change_code' => '000000']);
    check('Email: codigo incorreto nao altera conta', User::find($securityUserId)['email'] === 'security@example.test');
    $client->request('settings.email.confirm', ['email_change_code' => $mail['code']]);
    check('Email: codigo correto conclui troca', User::find($securityUserId)['email'] === $profile['email']);
    $client->request('settings.email.confirm', ['email_change_code' => $mail['code']]);
    check('Email: codigo consumido nao pode ser reutilizado', !isset($client->session()[1]['pending_email_change']));

    SecurityRateLimit::clear('email-send', $securityUserId);
    $pendingProfile = ['name' => 'Security Updated', 'email' => 'security-next@example.test', 'current_password' => $securityPassword];
    $client->request('settings.profile.update', $pendingProfile);
    approveAccountChange($client, $mailFile, $securityUserId);
    $expiredCode = securityTestMail($mailFile)['code'];
    [$sessionPath, $sessionData] = $client->session();
    $sessionData['pending_email_change']['expires_at'] = time() - 1;
    file_put_contents($sessionPath, serialize($sessionData));
    $client->request('settings.email.confirm', ['email_change_code' => $expiredCode]);
    check('Email: codigo expirado recusado', User::find($securityUserId)['email'] === $profile['email'] && !isset($client->session()[1]['pending_email_change']));

    SecurityRateLimit::clear('email-send', $securityUserId);
    $client->request('settings.profile.update', $pendingProfile);
    approveAccountChange($client, $mailFile, $securityUserId);
    $cancelledCode = securityTestMail($mailFile)['code'];
    $client->request('settings.email.cancel', []);
    $client->request('settings.email.confirm', ['email_change_code' => $cancelledCode]);
    check('Email: cancelar invalida o codigo', User::find($securityUserId)['email'] === $profile['email']);

    SecurityRateLimit::clear('email-send', $securityUserId);
    file_put_contents($failureFile, 'simulate failure');
    $client->request('settings.profile.update', $pendingProfile);
    approveAccountChange($client, $mailFile, $securityUserId);
    unlink($failureFile);
    check('Email: falha SMTP preserva endereco e nao cria confirmacao', User::find($securityUserId)['email'] === $profile['email'] && !isset($client->session()[1]['pending_email_change']));

    SecurityRateLimit::clear('email-send', $securityUserId);
    SecurityRateLimit::clear('email-send-window', $securityUserId);
    $client->request('settings.profile.update', $pendingProfile);
    approveAccountChange($client, $mailFile, $securityUserId);
    $limitedCode = securityTestMail($mailFile)['code'];
    for ($i = 0; $i < 5; $i++) $client->request('settings.email.confirm', ['email_change_code' => '000000']);
    $client->request('settings.email.confirm', ['email_change_code' => $limitedCode]);
    check('Email: limite de tentativas bloqueia inclusive codigo correto', User::find($securityUserId)['email'] === $profile['email']);
    $client->request('settings.email.cancel', []);

    $secret = 'JBSWY3DPEHPK3PXP';
    User::enableTwoFactor($securityUserId, $secret);
    $client->request('logout', []);
    $client->login($profile['email'], $securityPassword);
    for ($i = 0; $i < 5; $i++) $response = $client->request('login', ['two_factor_code' => 'invalid']);
    check('2FA: cinco erros bloqueiam com 429 e Retry-After', $response['status'] === 429 && str_contains($response['headers'], 'Retry-After:'));
    check('2FA: bloqueio encerra desafio pendente', !isset($client->session()[1]['pending_2fa_user_id']));
    $clients[] = $secondClient = new AccountSecurityHttpClient($baseUrl);
    $secondClient->login($profile['email'], $securityPassword);
    $response = $secondClient->request('login', ['two_factor_code' => TwoFactorAuth::currentCode($secret)]);
    check('2FA: novo navegador e senha correta nao reiniciam limite', $response['status'] === 429);
    securityTestExpire('2fa-login', $securityUserId);
    $secondClient->login($profile['email'], $securityPassword);
    $response = $secondClient->request('login', ['two_factor_code' => TwoFactorAuth::currentCode($secret)]);
    check('2FA: apos janela de bloqueio autenticador volta a funcionar', $response['status'] === 302 && $secondClient->request('settings.account')['status'] === 200);

    $secondClient->request('logout', []);
    User::enableEmailTwoFactor($securityUserId);
    SecurityRateLimit::clear('email-send', $securityUserId);
    SecurityRateLimit::clear('email-send-window', $securityUserId);
    $secondClient->login($profile['email'], $securityPassword);
    $secondClient->request('login.2fa.email', []);
    $mail = securityTestMail($mailFile);
    $response = $secondClient->request('login.2fa.email', []);
    check('2FA: reenvio imediato bloqueado sem novo email', $response['status'] === 429 && securityTestMail($mailFile)['count'] === $mail['count']);
    $clients[] = $thirdClient = new AccountSecurityHttpClient($baseUrl);
    $thirdClient->login($profile['email'], $securityPassword);
    $response = $thirdClient->request('login.2fa.email', []);
    check('2FA: intervalo de envio persiste em outro navegador', $response['status'] === 429);
    securityTestExpire('email-send', $securityUserId);
    $thirdClient->request('login.2fa.email', []);
    $freshCode = securityTestMail($mailFile)['code'];
    $response = $thirdClient->request('login', ['two_factor_code' => $mail['code']]);
    check('2FA: codigo de outra sessao nao autentica', $response['status'] !== 302 || $freshCode === $mail['code']);
    $response = $thirdClient->request('login', ['two_factor_code' => $freshCode]);
    check('2FA: codigo enviado por email autentica normalmente', $response['status'] === 302 && $thirdClient->request('settings.account')['status'] === 200);

    $clients[] = $adminClient = new AccountSecurityHttpClient($baseUrl);
    $adminClient->login('security-admin@example.test', 'Security-Admin-Password-123');
    User::create(['name' => 'Audit Viewer', 'email' => 'audit-viewer@example.test', 'password' => 'Audit-Viewer-Test-123', 'role' => 'viewer', 'is_active' => 1]);
    $clients[] = $auditViewer = new AccountSecurityHttpClient($baseUrl);
    check('Arquivos de auditoria: conta comum autentica', $auditViewer->login('audit-viewer@example.test', 'Audit-Viewer-Test-123')['status'] === 302);
    check('Arquivos de auditoria: usuario comum recusado', $auditViewer->request('settings.auditFiles')['status'] === 403);
    check('Sessoes: usuario comum recusado', $auditViewer->request('sessions.index')['status'] === 403);
    check('Sessoes: administrador fora do cofre recusado', $adminClient->request('sessions.index')['status'] === 403);
    $auditResponse = $adminClient->request('settings.auditFiles');
    check('Arquivos de auditoria: admin precisa confirmar senha', $auditResponse['status'] === 200 && !str_contains($auditResponse['body'], 'recorded_at'));
    $auditResponse = $adminClient->request('settings.auditFiles', ['password' => 'wrong', 'date' => gmdate('Y-m-d')]);
    check('Arquivos de auditoria: senha errada nao revela registros', !str_contains($auditResponse['body'], 'recorded_at'));
    $auditResponse = $adminClient->request('settings.auditFiles', ['password' => 'Security-Admin-Password-123', 'date' => gmdate('Y-m-d')]);
    check('Arquivos de auditoria: senha correta libera consulta sem cache', str_contains($auditResponse['body'], 'recorded_at') && stripos($auditResponse['headers'], 'no-store') !== false);
    $adminClient->request('users.update', ['id' => $securityAdminId, 'name' => 'Security Admin', 'email' => 'bypass@example.test', 'role' => 'admin']);
    check('Email: painel administrativo nao permite contornar verificacao da propria conta', User::find($securityAdminId)['email'] === 'security-admin@example.test');
    $adminClient->request('users.resetPassword', ['id' => $securityAdminId, 'password' => 'Self-Reset-Bypass-123', 'password_confirmation' => 'Self-Reset-Bypass-123']);
    check('Senha: reset administrativo da propria conta exige fluxo com senha atual', password_verify('Security-Admin-Password-123', User::find($securityAdminId)['password_hash']));
    $clients[] = $pendingClient = new AccountSecurityHttpClient($baseUrl);
    $pendingClient->login($profile['email'], $securityPassword);
    securityTestExpire('email-send', $securityUserId);
    $pendingClient->request('login.2fa.email', []);
    $resetCode = securityTestMail($mailFile)['code'];
    $apiToken = ApiToken::generatePlainToken();
    ApiToken::create($securityUserId, 'Reset regression token', $apiToken);
    $newPassword = 'New-Security-Password-123';
    $userBeforeReset = User::find($securityUserId);
    $response = $adminClient->request('users.resetPassword', ['id' => $securityUserId, 'password' => $newPassword, 'password_confirmation' => $newPassword]);
    check('Confirmacao: operacao aguarda segundo fator', str_contains($response['headers'], 'account.challenge'));
    $response = approveAccountChange($adminClient, $mailFile, $securityAdminId);
    check('Reset: administrador redefine senha e remove sessao no banco', $response['status'] === 302 && empty(User::find($securityUserId)['active_session_token']));
    check('Reset: login concorrente com dados antigos nao consegue criar sessao', !User::setActiveSession($securityUserId, bin2hex(random_bytes(32)), $userBeforeReset) && empty(User::find($securityUserId)['active_session_token']));
    $response = $thirdClient->request('settings.account');
    check('Reset: navegador antes autenticado perde acesso', $response['status'] === 302 && str_contains($response['headers'], 'route=login'));
    check('Reset: tokens antigos sao revogados', ApiToken::findActiveByPlainToken($apiToken) === null);
    $response = $pendingClient->request('login', ['two_factor_code' => $resetCode]);
    check('Reset: desafio 2FA iniciado com senha antiga e invalidado', $response['status'] !== 302 && empty(User::find($securityUserId)['active_session_token']));
    $response = $pendingClient->login($profile['email'], $securityPassword);
    check('Reset: senha antiga nao inicia novo desafio 2FA', !isset($pendingClient->session()[1]['pending_2fa_user_id']));
    User::disableTwoFactor($securityUserId);
    check('Reset: nova senha permite entrar', $pendingClient->login($profile['email'], $newPassword)['status'] === 302);
    $response = $pendingClient->request('settings.password.update', ['current_password' => $newPassword, 'password' => $securityPassword, 'password_confirmation' => $securityPassword]);
    check('Confirmacao: operacao aguarda segundo fator', str_contains($response['headers'], 'account.challenge'));
    $response = approveAccountChange($pendingClient, $mailFile, $securityUserId);
    check('Senha: troca pelo proprio usuario encerra sessao e volta ao login', $response['status'] === 302 && str_contains($response['headers'], 'route=login') && empty(User::find($securityUserId)['active_session_token']));
    check('Senha: login funciona apos a troca', $pendingClient->login($profile['email'], $securityPassword)['status'] === 302);

    $proofSecret = TwoFactorAuth::generateSecret();
    User::enableTwoFactor($securityUserId, $proofSecret);
    SecurityRateLimit::clear('account-change-proof', $securityUserId);
    $pendingClient->request('settings.2fa.disable', ['password' => $securityPassword]);
    check('2FA: somente senha nao desativa', (int) User::find($securityUserId)['two_factor_enabled'] === 1);
    $pendingClient->request('account.challenge', ['method' => 'totp', 'code' => '000000']);
    check('2FA: codigo incorreto nao desativa', (int) User::find($securityUserId)['two_factor_enabled'] === 1);
    $pendingClient->request('account.challenge', ['method' => 'totp', 'code' => TwoFactorAuth::currentCode($proofSecret)]);
    check('2FA: autenticador cadastrado autoriza desativacao', (int) User::find($securityUserId)['two_factor_enabled'] === 0);
    check('Confirmacao: desafio consumido', !isset($pendingClient->session()[1]['account_challenge']));
    User::enableEmailTwoFactor($securityUserId);
    $pendingClient->request('settings.2fa.disable', ['password' => $securityPassword]);
    approveAccountChange($pendingClient, $mailFile, $securityUserId);
    check('2FA: codigo no email atual autoriza desativacao', (int) User::find($securityUserId)['two_factor_enabled'] === 0);
    $pendingClient->request('settings.password.update', ['current_password' => $securityPassword, 'password' => 'Unapplied-Password-123', 'password_confirmation' => 'Unapplied-Password-123']);
    [$proofPath, $proofSession] = $pendingClient->session();
    check('Confirmacao: senha pendente nao fica em claro na sessao', !str_contains(serialize($proofSession), 'Unapplied-Password-123'));
    $proofSession['account_challenge']['expires'] = time() - 1;
    file_put_contents($proofPath, serialize($proofSession));
    $pendingClient->request('account.challenge', ['method' => 'email', 'code' => '123456']);
    check('Confirmacao: expiracao preserva senha anterior', password_verify($securityPassword, User::find($securityUserId)['password_hash']) && !isset($pendingClient->session()[1]['account_challenge']));

    $audit = db()->query("SELECT user_id FROM audit_logs WHERE action_type = 'user_password_reset' ORDER BY id DESC LIMIT 1")->fetchColumn();
    check('Auditoria: reset registra administrador responsavel', (int) $audit === $securityAdminId);
    $auditContents = json_encode(db()->query('SELECT old_data, new_data FROM audit_logs')->fetchAll());
    check('Auditoria: novas operacoes nao gravam senhas ou tokens em claro', !str_contains($auditContents, $securityPassword) && !str_contains($auditContents, $newPassword) && !str_contains($auditContents, $apiToken));
    if (getenv('SECURITY_UI_TEST') === '1') {
        User::create(['name' => 'UI Test', 'email' => 'security-ui@example.test', 'password' => 'Security-UI-Password-123', 'role' => 'viewer', 'is_active' => 1]);
        $ui = proc_open(['node', __DIR__ . '/account_security_ui.cjs', $baseUrl, $mailFile], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $uiPipes, BASE_PATH);
        fclose($uiPipes[0]);
        echo stream_get_contents($uiPipes[1]);
        echo stream_get_contents($uiPipes[2]);
        fclose($uiPipes[1]);
        fclose($uiPipes[2]);
        check('Interface: confirmacoes funcionam no computador e celular', proc_close($ui) === 0);
    }
    require __DIR__ . '/user_permissions.php';
    $securityTestCompleted = true;
} finally {
    foreach (array_reverse($processes) as $process) {
        if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    }
    if ($failed > $initialFailures || !$securityTestCompleted) {
        echo implode('', array_slice(file($serverLog) ?: [], -35));
    }
    foreach ($clients as $client) {
        foreach (array_unique($client->sessionIds) as $sessionId) {
            if (preg_match('/^[A-Za-z0-9,-]+$/D', $sessionId)) {
                $path = STORAGE_PATH . '/sessions/sess_' . $sessionId;
                if (is_file($path)) unlink($path);
            }
        }
    }
    foreach ([$mailFile, $failureFile, $serverLog] as $path) {
        if (is_file($path)) unlink($path);
    }
}
