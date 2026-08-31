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
                LoginAttempt::clear($email, $ipAddress);

                if (!empty($user['two_factor_enabled'])) {
                    $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
                    $_SESSION['pending_2fa_started_at'] = time();
                    view('auth/login', [
                        'title' => 'Login',
                        'requiresTwoFactor' => true,
                        'twoFactorUserEmail' => $user['email'],
                    ]);
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

        $pendingUser = !empty($_SESSION['pending_2fa_user_id'])
            ? User::find((int) $_SESSION['pending_2fa_user_id'])
            : null;
        view('auth/login', [
            'title' => 'Login',
            'requiresTwoFactor' => $pendingUser !== null,
            'twoFactorUserEmail' => $pendingUser['email'] ?? null,
            'emailCodeSent' => !empty($_SESSION['pending_2fa_email_code_hash']),
        ]);
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
        unset(
            $_SESSION['pending_2fa_user_id'],
            $_SESSION['pending_2fa_started_at'],
            $_SESSION['pending_2fa_email_code_hash'],
            $_SESSION['pending_2fa_email_code_expires_at']
        );
        flash('success', 'Verificação 2FA cancelada.');
        redirect('/?route=login');
    }

    public static function sendTwoFactorEmailCode(): void
    {
        verify_csrf();

        $user = User::find((int) ($_SESSION['pending_2fa_user_id'] ?? 0));
        $startedAt = (int) ($_SESSION['pending_2fa_started_at'] ?? 0);
        if (!$user || empty($user['is_active']) || time() - $startedAt > 300) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at']);
            flash('danger', 'Validação 2FA expirada. Faça login novamente.');
            redirect('/?route=login');
        }

        $code = EmailCode::generate();
        $_SESSION['pending_2fa_email_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['pending_2fa_email_code_expires_at'] = time() + 600;

        if (!EmailCode::sendLoginCode($user, $code)) {
            unset($_SESSION['pending_2fa_email_code_hash'], $_SESSION['pending_2fa_email_code_expires_at']);
            flash('danger', 'Não foi possível enviar o código por e-mail. Verifique a configuração de e-mail do servidor.');
            redirect('/?route=login');
        }

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
        view('auth/login', [
            'title' => 'Login',
            'requiresTwoFactor' => true,
            'twoFactorUserEmail' => $user['email'],
            'emailCodeSent' => true,
        ]);
    }

    private static function verifyTwoFactorLogin(): void
    {
        $user = User::find((int) ($_SESSION['pending_2fa_user_id'] ?? 0));
        $startedAt = (int) ($_SESSION['pending_2fa_started_at'] ?? 0);
        $code = (string) ($_POST['two_factor_code'] ?? '');

        if (!$user || empty($user['is_active']) || time() - $startedAt > 300) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_started_at']);
            flash('danger', 'Validação 2FA expirada. Faça login novamente.');
            view('auth/login', ['title' => 'Login']);
            return;
        }

        $secret = User::twoFactorSecret($user);
        $emailCodeHash = $_SESSION['pending_2fa_email_code_hash'] ?? null;
        $emailCodeExpiresAt = (int) ($_SESSION['pending_2fa_email_code_expires_at'] ?? 0);
        $emailCodeValid = is_string($emailCodeHash)
            && $emailCodeExpiresAt >= time()
            && password_verify(preg_replace('/\s+/', '', $code) ?? '', $emailCodeHash);

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
            flash('danger', 'Código de autenticação inválido.');
            view('auth/login', [
                'title' => 'Login',
                'requiresTwoFactor' => true,
                'twoFactorUserEmail' => $user['email'],
                'emailCodeSent' => is_string($emailCodeHash),
            ]);
            return;
        }

        unset(
            $_SESSION['pending_2fa_user_id'],
            $_SESSION['pending_2fa_started_at'],
            $_SESSION['pending_2fa_email_code_hash'],
            $_SESSION['pending_2fa_email_code_expires_at']
        );
        self::completeLogin($user);
    }

    private static function completeLogin(array $user): void
    {
        session_regenerate_id(true);
        $sessionToken = bin2hex(random_bytes(32));
        User::setActiveSession((int) $user['id'], $sessionToken);
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_admin' => (int) ($user['is_admin'] ?? 0),
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
