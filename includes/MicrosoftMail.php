<?php
declare(strict_types=1);

final class MicrosoftMail
{
    private const KEY = 'microsoft_mail_tokens';
    private const SCOPE = 'https://graph.microsoft.com/User.Read https://graph.microsoft.com/Mail.Send offline_access';

    public static function config(): array
    {
        $file = BASE_PATH . '/config/microsoft.local.php';
        return is_file($file) ? (array) require $file : [];
    }

    public static function ready(): bool
    {
        $c = self::config();
        return preg_match('/^[a-f0-9-]{36}$/i', $c['tenant_id'] ?? '') === 1
            && preg_match('/^[a-f0-9-]{36}$/i', $c['client_id'] ?? '') === 1
            && !empty($c['client_secret'])
            && self::validRedirectUri($c['redirect_uri'] ?? '')
            && filter_var($c['sender'] ?? '', FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validRedirectUri(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($uri);
        if (!$parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        return $scheme === 'https' || ($scheme === 'http' && $host === 'localhost');
    }

    public static function connected(): bool
    {
        return AppSetting::get(self::KEY) !== '';
    }

    public static function disconnect(): void
    {
        $lock = fopen(STORAGE_PATH . '/microsoft-mail.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Nao foi possivel desconectar. Tente novamente.');
        }
        try {
            AppSetting::set(self::KEY, '');
            unset($_SESSION['microsoft_oauth']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function authorize(): string
    {
        if (!self::ready()) {
            throw new RuntimeException('Preencha config/microsoft.local.php antes de conectar.');
        }
        $c = self::config();
        $verifier = bin2hex(random_bytes(32));
        $state = bin2hex(random_bytes(32));
        $_SESSION['microsoft_oauth'] = ['state' => $state, 'verifier' => $verifier, 'expires' => time() + 600,
            'user_id' => current_user()['id'], 'redirect_uri' => $c['redirect_uri']];
        return 'https://login.microsoftonline.com/' . $c['tenant_id'] . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id' => $c['client_id'], 'response_type' => 'code', 'response_mode' => 'query',
            'redirect_uri' => $c['redirect_uri'], 'scope' => self::SCOPE, 'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'prompt' => 'select_account', 'login_hint' => $c['sender'],
        ]);
    }

    public static function callback(): void
    {
        $pending = $_SESSION['microsoft_oauth'] ?? [];
        unset($_SESSION['microsoft_oauth']);
        if (!is_string($_GET['state'] ?? null) || empty($pending['state'])
            || !hash_equals($pending['state'], $_GET['state']) || ($pending['expires'] ?? 0) < time()
            || ($pending['user_id'] ?? null) !== current_user()['id']
            || ($pending['redirect_uri'] ?? '') !== (self::config()['redirect_uri'] ?? '')
            || !is_string($_GET['code'] ?? null) || $_GET['code'] === '') {
            throw new RuntimeException('Autorizacao cancelada ou expirada. Conecte novamente.');
        }
        $tokens = self::token(['grant_type' => 'authorization_code', 'code' => $_GET['code'],
            'code_verifier' => $pending['verifier'], 'redirect_uri' => $pending['redirect_uri']]);
        $profile = self::request('https://graph.microsoft.com/v1.0/me?$select=id,mail,userPrincipalName', null, $tokens['access_token']);
        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? '';
        if (strcasecmp($email, self::config()['sender']) !== 0 || empty($profile['id']) || empty($tokens['refresh_token'])) {
            throw new RuntimeException('Conecte somente a conta remetente configurada e autorize acesso offline.');
        }
        $tokens['account_id'] = $profile['id'];
        self::save($tokens);
    }

    private static function save(array $tokens): void
    {
        $tokens['expires_at'] = time() + (int) ($tokens['expires_in'] ?? 3600);
        $tokens['config_id'] = hash('sha256', json_encode(array_intersect_key(self::config(), array_flip(['tenant_id', 'client_id', 'sender']))));
        AppSetting::set(self::KEY, (string) CredentialCrypto::encrypt(json_encode($tokens, JSON_THROW_ON_ERROR), 'microsoft'));
    }

    private static function token(array $params): array
    {
        if (!self::ready()) {
            throw new RuntimeException('Configuracao Microsoft incompleta.');
        }
        $c = self::config();
        $data = self::request('https://login.microsoftonline.com/' . $c['tenant_id'] . '/oauth2/v2.0/token',
            http_build_query($params + ['client_id' => $c['client_id'], 'client_secret' => $c['client_secret'], 'scope' => self::SCOPE]));
        if (empty($data['access_token'])) {
            throw new RuntimeException('Microsoft nao retornou autorizacao. Reconecte a conta.');
        }
        return $data;
    }

    public static function send(string $to, string $subject, string $body, bool $html = false): bool
    {
        // Serialize refreshes and disconnects across PHP workers.
        $lock = fopen(STORAGE_PATH . '/microsoft-mail.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX)) {
            return false;
        }
        try {
            $tokens = json_decode((string) CredentialCrypto::decrypt(AppSetting::get(self::KEY), 'microsoft'), true, 512, JSON_THROW_ON_ERROR);
            $configId = hash('sha256', json_encode(array_intersect_key(self::config(), array_flip(['tenant_id', 'client_id', 'sender']))));
            if (!$tokens || !hash_equals($tokens['config_id'] ?? '', $configId)) {
                return false;
            }
            if (($tokens['expires_at'] ?? 0) < time() + 120) {
                $fresh = self::token(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]);
                $tokens = array_replace($tokens, $fresh);
                self::save($tokens);
            }
            $message = ['subject' => $subject, 'body' => ['contentType' => $html ? 'HTML' : 'Text', 'content' => $body],
                'toRecipients' => [['emailAddress' => ['address' => $to]]]];
            if ($html) {
                $message['attachments'] = [['@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => 'exe-logo.png', 'contentType' => 'image/png', 'contentId' => 'exe-logo',
                    'isInline' => true, 'contentBytes' => base64_encode(CodeEmailTemplate::logo())]];
            }
            self::request('https://graph.microsoft.com/v1.0/me/sendMail', json_encode([
                'message' => $message, 'saveToSentItems' => true,
            ], JSON_THROW_ON_ERROR), $tokens['access_token']);
            return true;
        } catch (Throwable $error) {
            error_log('Microsoft mail failed; check connection and configuration.');
            return false;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function request(string $url, ?string $body = null, ?string $token = null): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $headers[] = 'Content-Type: ' . ($token === null ? 'application/x-www-form-urlencoded' : 'application/json');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 25, CURLOPT_HTTPHEADER => $headers, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($result === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Microsoft indisponivel ou autorizacao recusada (HTTP ' . $status . '). Verifique a configuracao ou reconecte.');
        }
        return $result === '' ? [] : json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }
}
