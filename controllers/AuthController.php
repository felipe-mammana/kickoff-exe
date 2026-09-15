<?php

declare(strict_types=1);

class AuthController
{
    public static function login(): void
    {
        if (current_user()) {
            redirect('/');
        }

        if (is_post()) {
            verify_csrf();

            if (!empty($_SESSION['pending_2fa_user_id'])) {
                self::verifyTwoFactorLogin();
                return;
            }

            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $ipAddress = client_ip();

            if (LoginAttempt::isBlocked($email, $ipAddress)) {
                AuditLog::record([
                    'user_email' => $email,
                    'action_type' => 'login_rate_limited',
                    'affected_table' => 'users',
                    'description' => 'Tentativa de login bloqueada por excesso de falhas.',
                    'new_data' => [
                        'email' => $email,
                        'remaining_seconds' => LoginAttempt::remainingSeconds($email, $ipAddress),
                    ],
                ]);
                flash('danger', 'Muitas tentativas. Aguarde alguns minutos e tente novamente.');
                view('auth/login', ['title' => 'Login']);
                return;
            }

            $user = User::findByEmail($email);

            if ($user && empty($user['is_active'])) {
                LoginAttempt::recordFailure($email, $ipAddress);
                AuditLog::record([
                    'user_id' => (int) $user['id'],
                    'user_name' => $user['name'] ?? null,
                    'user_email' => $email,
                    'action_type' => 'login_inactive_user',
                    'affected_table' => 'users',
                    'affected_record_id' => (int) $user['id'],
                    'description' => 'Tentativa de login de usuario desativado.',
                    'new_data' => ['email' => $email],
                ]);
                flash('danger', 'E-mail ou senha inválidos.');
                view('auth/login', ['title' => 'Login']);
                return;
            } elseif ($user && password_verify($password, $user['password_hash'])) {
                if (password_needs_rehash($user['password_hash'], PasswordSecurity::algorithm(), PasswordSecurity::options())) {
                    $hash = PasswordSecurity::hash($password);
                    $stmt = db()->prepare('UPDATE users SET password_hash = :hash WHERE id = :id AND password_hash = :old');
                    $stmt->execute(['hash' => $hash, 'id' => $user['id'], 'old' => $user['password_hash']]);
                    if ($stmt->rowCount() !== 1) {
                        redirect('/?route=login');
                        return;
                    }
                    $user['password_hash'] = $hash;
                }
                LoginAttempt::clear($email, $ipAddress);

                if (!empty($user['two_factor_enabled'])) {
                    self::clearPendingTwoFactor();
                    session_regenerate_id(true);
                    $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
                    $_SESSION['pending_2fa_started_at'] = time();
                    $_SESSION['pending_2fa_fingerprint'] = User::authenticationFingerprint($user);
                    self::renderTwoFactor($user);
                    return;
                }

                self::completeLogin($user);
            }

            LoginAttempt::recordFailure($email, $ipAddress);
            AuditLog::record([
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
                'user_email' => $email,
                'action_type' => 'login_failed',
                'affected_table' => 'users',
                'affected_record_id' => $user['id'] ?? null,
                'description' => 'Tentativa de login com dados incorretos.',
                'new_data' => ['email' => $email],
            ]);
            flash('danger', 'E-mail ou senha inválidos.');
        }

        $pendingUser = self::pendingTwoFactorUser();
        if ($pendingUser) {
            self::renderTwoFactor($pendingUser);
        } else {
            view('auth/login', ['title' => 'Login']);
        }
    }

    public static function logout(): void
    {
        verify_csrf();
        $userId = current_user()['id'] ?? null;
        $sessionToken = $_SESSION['session_token'] ?? null;
        AuditLog::record([
            'action_type' => 'logout',
            'affected_table' => 'users',
            'affected_record_id' => $userId,
            'description' => 'Usuário saiu do sistema.',
        ]);
        if ($userId) {
            User::clearActiveSession((int) $userId, is_string($sessionToken) ? $sessionToken : null);
        }

        $_SESSION = [];
        session_destroy();
        redirect('/?route=login');
    }

    public static function cancelTwoFactor(): void
    {
        verify_csrf();
        self::clearPendingTwoFactor();
        flash('success', 'Verificação 2FA cancelada.');
        redirect('/?route=login');
    }

    public static function sendTwoFactorEmailCode(): void
    {
        verify_csrf();

        $user = self::pendingTwoFactorUser();
        if (!$user) {
            flash('danger', 'Validação 2FA expirada. Faça login novamente.');
            redirect('/?route=login');
        }

        $retryAfter = SecurityRateLimit::retryAfter('2fa-login', (int) $user['id']);
        if ($retryAfter > 0) {
            self::blockTwoFactor($user, $retryAfter);
            return;
        }
        $limit = SecurityRateLimit::emailSend((int) $user['id']);
        if (!$limit['allowed']) {
            http_response_code(429);
            header('Retry-After: ' . $limit['retry_after']);
            flash('danger', 'Aguarde ' . $limit['retry_after'] . ' segundos antes de solicitar outro e-mail.');
            self::renderTwoFactor($user);
            return;
        }
        $code = EmailCode::generate();
        $expiresAt = (int) $_SESSION['pending_2fa_started_at'] + 300;
        if (!EmailCode::sendLoginCode($user, $code, max(1, $expiresAt - time()))) {
            flash('danger', 'Não foi possível enviar o código por e-mail. Verifique a configuração de e-mail do servidor.');
            redirect('/?route=login');
        }
        $_SESSION['pending_2fa_email_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['pending_2fa_email_code_expires_at'] = $expiresAt;

        AuditLog::record([
            'user_id' => (int) $user['id'],
            'user_name' => $user['name'] ?? null,
            'user_email' => $user['email'] ?? null,
            'action_type' => 'login_2fa_email_sent',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Código 2FA enviado por e-mail.',
        ]);

        flash('success', 'Código enviado para o e-mail cadastrado.');
        self::renderTwoFactor($user);
    }

    private static function verifyTwoFactorLogin(): void
    {
        $user = self::pendingTwoFactorUser();
        $code = preg_replace('/\s+/', '', (string) ($_POST['two_factor_code'] ?? '')) ?? '';

        if (!$user) {
            flash('danger', 'Validação 2FA expirada. Faça login novamente.');
            view('auth/login', ['title' => 'Login']);
            return;
        }

        $limit = SecurityRateLimit::hit('2fa-login', (int) $user['id']);
        if (!$limit['allowed']) {
            self::blockTwoFactor($user, $limit['retry_after']);
            return;
        }
        $secret = User::twoFactorSecret($user);
        $emailCodeHash = $_SESSION['pending_2fa_email_code_hash'] ?? null;
        $emailCodeExpiresAt = (int) ($_SESSION['pending_2fa_email_code_expires_at'] ?? 0);
        $emailCodeValid = is_string($emailCodeHash)
            && $emailCodeExpiresAt > time()
            && preg_match('/^[0-9]{6}$/D', $code)
            && password_verify($code, $emailCodeHash);

        if ((!$secret || !TwoFactorAuth::verify($secret, $code)) && !$emailCodeValid) {
            AuditLog::record([
                'user_id' => (int) $user['id'],
                'user_name' => $user['name'] ?? null,
                'user_email' => $user['email'] ?? null,
                'action_type' => 'login_2fa_failed',
                'affected_table' => 'users',
                'affected_record_id' => (int) $user['id'],
                'description' => 'Código 2FA inválido no login.',
            ]);
            if ($limit['remaining'] === 0) {
                self::blockTwoFactor($user, $limit['retry_after']);
                return;
            }
            flash('danger', 'Código inválido. Restam ' . $limit['remaining'] . ' tentativa(s).');
            self::renderTwoFactor($user);
            return;
        }

        SecurityRateLimit::clear('2fa-login', (int) $user['id']);
        self::clearPendingTwoFactor();
        self::completeLogin($user);
    }

    private static function clearPendingTwoFactor(): void
    {
        unset(
            $_SESSION['pending_2fa_user_id'],
            $_SESSION['pending_2fa_started_at'],
            $_SESSION['pending_2fa_fingerprint'],
            $_SESSION['pending_2fa_email_code_hash'],
            $_SESSION['pending_2fa_email_code_expires_at']
        );
    }

    private static function pendingTwoFactorUser(): ?array
    {
        $user = User::find((int) ($_SESSION['pending_2fa_user_id'] ?? 0));
        if (!$user || empty($user['is_active']) || empty($user['two_factor_enabled'])
            || (int) ($_SESSION['pending_2fa_started_at'] ?? 0) + 300 <= time()
            || !hash_equals(User::authenticationFingerprint($user), (string) ($_SESSION['pending_2fa_fingerprint'] ?? ''))
        ) {
            self::clearPendingTwoFactor();
            return null;
        }
        return $user;
    }

    private static function renderTwoFactor(array $user): void
    {
        view('auth/login', [
            'title' => 'Login', 'requiresTwoFactor' => true,
            'twoFactorUserEmail' => $user['email'],
            'twoFactorHasAuthenticator' => User::twoFactorSecret($user) !== null,
            'emailCodeSent' => !empty($_SESSION['pending_2fa_email_code_hash']),
            'emailRetryAfter' => max(
                SecurityRateLimit::retryAfter('email-send', (int) $user['id'], 1),
                SecurityRateLimit::retryAfter('email-send-window', (int) $user['id'])
            ),
        ]);
    }

    private static function blockTwoFactor(array $user, int $retryAfter): void
    {
        self::clearPendingTwoFactor();
        http_response_code(429);
        header('Retry-After: ' . $retryAfter);
        AuditLog::record([
            'user_id' => (int) $user['id'], 'user_name' => $user['name'], 'user_email' => $user['email'],
            'action_type' => 'login_2fa_rate_limited', 'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Verificação 2FA bloqueada por excesso de tentativas.',
        ]);
        flash('danger', 'Limite de tentativas do 2FA atingido. Aguarde ' . $retryAfter . ' segundos e entre novamente.');
        view('auth/login', ['title' => 'Login']);
    }

    private static function completeLogin(array $user): void
    {
        session_regenerate_id(true);
        $sessionToken = bin2hex(random_bytes(32));
        if (!User::setActiveSession((int) $user['id'], $sessionToken, $user)) {
            $_SESSION = [];
            flash('danger', 'A conta foi alterada durante a autenticação. Entre novamente.');
            redirect('/?route=login');
        }
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_admin' => (int) ($user['is_admin'] ?? 0),
            'role' => User::roleFromUser($user),
            'two_factor_enabled' => (int) ($user['two_factor_enabled'] ?? 0),
        ];
        AuditLog::record([
            'action_type' => 'login_success',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário entrou no sistema.',
        ]);
        redirect('/');
    }
}
