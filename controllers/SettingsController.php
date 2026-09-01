<?php

declare(strict_types=1);

class SettingsController
{
    public static function index(): void
    {
        require_auth();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $setupSecret = $_SESSION['two_factor_setup_secret'] ?? null;
        if (!is_string($setupSecret) || $setupSecret === '') {
            $setupSecret = null;
        }

        view('settings/index', [
            'title' => 'Configurações',
            'accountUser' => $user,
            'twoFactorSetupSecret' => $setupSecret,
            'twoFactorProvisioningUri' => $setupSecret
                ? TwoFactorAuth::provisioningUri((string) $user['email'], APP_NAME, $setupSecret)
                : null,
            'activeSessions' => self::activeSessions($user),
            'recentAccesses' => AuditLog::latestAccountAccesses((int) $user['id']),
            'maintenanceStatus' => is_admin() ? DatabaseMaintenance::status() : null,
        ]);
    }

    public static function prepareTwoFactor(): void
    {
        require_auth();
        verify_csrf();

        $_SESSION['two_factor_setup_secret'] = TwoFactorAuth::generateSecret();
        redirect('/?route=settings.index');
    }

    public static function updateProfile(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));

        if ($name === '' || strlen($name) > 120) {
            flash('danger', 'Informe um nome válido com até 120 caracteres.');
            redirect('/?route=settings.index');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
            flash('danger', 'Informe um e-mail válido.');
            redirect('/?route=settings.index');
        }

        if (User::duplicateEmailExists($email, (int) $user['id'])) {
            flash('danger', 'Este e-mail já está em uso por outro usuário.');
            redirect('/?route=settings.index');
        }

        User::updateProfile((int) $user['id'], $name, $email);
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;

        AuditLog::record([
            'action_type' => 'user_profile_updated',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário atualizou o próprio perfil.',
            'old_data' => [
                'name' => $user['name'],
                'email' => $user['email'],
            ],
            'new_data' => [
                'name' => $name,
                'email' => $email,
            ],
        ]);

        flash('success', 'Perfil atualizado com sucesso.');
        redirect('/?route=settings.index');
    }

    public static function updatePassword(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            flash('danger', 'Senha atual inválida.');
            redirect('/?route=settings.index');
        }

        if (strlen($password) < 8) {
            flash('danger', 'A nova senha deve ter no mínimo 8 caracteres.');
            redirect('/?route=settings.index');
        }

        if ($password !== $confirmation) {
            flash('danger', 'A confirmação da nova senha não confere.');
            redirect('/?route=settings.index');
        }

        User::updatePassword((int) $user['id'], $password);

        AuditLog::record([
            'action_type' => 'user_password_changed',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário alterou a própria senha.',
        ]);

        flash('success', 'Senha alterada com sucesso.');
        redirect('/?route=settings.index');
    }

    public static function updatePreferences(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $theme = (string) ($_POST['preferred_theme'] ?? 'light');
        $sidebar = (string) ($_POST['sidebar_default'] ?? 'expanded');
        $pageSize = (int) ($_POST['table_page_size'] ?? 25);
        $datetimeFormat = (string) ($_POST['datetime_format'] ?? 'd/m/Y H:i');

        $allowedThemes = ['light', 'dark'];
        $allowedSidebars = ['expanded', 'collapsed'];
        $allowedPageSizes = [10, 25, 50, 100];
        $allowedDateFormats = ['d/m/Y H:i', 'd/m/Y', 'Y-m-d H:i', 'Y-m-d'];

        if (!in_array($theme, $allowedThemes, true)
            || !in_array($sidebar, $allowedSidebars, true)
            || !in_array($pageSize, $allowedPageSizes, true)
            || !in_array($datetimeFormat, $allowedDateFormats, true)
        ) {
            flash('danger', 'Preferências inválidas.');
            redirect('/?route=settings.index');
        }

        $preferences = [
            'preferred_theme' => $theme,
            'sidebar_default' => $sidebar,
            'table_page_size' => $pageSize,
            'datetime_format' => $datetimeFormat,
        ];

        User::updatePreferences((int) $user['id'], $preferences);

        foreach ($preferences as $key => $value) {
            $_SESSION['user'][$key] = $value;
        }

        AuditLog::record([
            'action_type' => 'user_preferences_updated',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário atualizou preferências do sistema.',
            'old_data' => [
                'preferred_theme' => $user['preferred_theme'] ?? 'light',
                'sidebar_default' => $user['sidebar_default'] ?? 'expanded',
                'table_page_size' => (int) ($user['table_page_size'] ?? 25),
                'datetime_format' => $user['datetime_format'] ?? 'd/m/Y H:i',
            ],
            'new_data' => $preferences,
        ]);

        flash('success', 'Preferências salvas com sucesso.');
        redirect('/?route=settings.index');
    }

    public static function updateSecurityPreferences(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $timeout = (int) ($_POST['session_timeout_minutes'] ?? 480);
        $allowedTimeouts = [30, 60, 120, 240, 480, 720, 1440];
        if (!in_array($timeout, $allowedTimeouts, true)) {
            flash('danger', 'Tempo de sessão inválido.');
            redirect('/?route=settings.index');
        }

        $requirePassword = !empty($_POST['vault_require_password_reveal']);
        User::updateSecurityPreferences((int) $user['id'], $timeout, $requirePassword);
        $_SESSION['user']['session_timeout_minutes'] = $timeout;
        $_SESSION['user']['vault_require_password_reveal'] = $requirePassword ? 1 : 0;

        AuditLog::record([
            'action_type' => 'user_security_preferences_updated',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário atualizou preferências de segurança.',
            'old_data' => [
                'session_timeout_minutes' => (int) ($user['session_timeout_minutes'] ?? 480),
                'vault_require_password_reveal' => (int) ($user['vault_require_password_reveal'] ?? 0),
            ],
            'new_data' => [
                'session_timeout_minutes' => $timeout,
                'vault_require_password_reveal' => $requirePassword ? 1 : 0,
            ],
        ]);

        flash('success', 'Preferências de segurança salvas.');
        redirect('/?route=settings.index');
    }

    public static function endOtherSessions(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        $token = (string) ($_SESSION['session_token'] ?? '');
        if (!$user || $token === '') {
            redirect('/?route=login');
        }

        User::setActiveSession((int) $user['id'], $token);

        AuditLog::record([
            'action_type' => 'user_sessions_revoked',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário manteve a sessão atual e invalidou outras sessões.',
        ]);

        flash('success', 'Outras sessões foram encerradas. A sessão atual foi mantida.');
        redirect('/?route=settings.index');
    }

    public static function createApiToken(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $name = trim((string) ($_POST['api_token_name'] ?? ''));
        $days = (int) ($_POST['api_token_days'] ?? 90);

        if ($name === '' || strlen($name) > 120) {
            flash('danger', 'Informe um nome válido para o token.');
            redirect('/?route=settings.index');
        }

        if (!in_array($days, [7, 30, 90, 180, 365], true)) {
            flash('danger', 'Validade do token inválida.');
            redirect('/?route=settings.index');
        }

        $plainToken = ApiToken::generatePlainToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
        $tokenId = ApiToken::create((int) $user['id'], $name, $plainToken, $expiresAt);
        $_SESSION['generated_api_token'] = [
            'name' => $name,
            'token' => $plainToken,
            'expires_at' => $expiresAt,
        ];

        AuditLog::record([
            'action_type' => 'api_token_created',
            'affected_table' => 'api_tokens',
            'affected_record_id' => $tokenId,
            'description' => 'Usuário gerou token de API.',
            'new_data' => [
                'name' => $name,
                'expires_at' => $expiresAt,
            ],
        ]);

        flash('success', 'Token de API criado. Copie o valor exibido agora; ele não será mostrado novamente.');
        redirect('/?route=settings.index');
    }

    public static function revokeApiToken(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        $tokenId = (int) ($_POST['id'] ?? 0);
        if ($tokenId <= 0 || !ApiToken::revoke($tokenId, (int) $user['id'])) {
            flash('danger', 'Token não encontrado ou já revogado.');
            redirect('/?route=settings.index');
        }

        AuditLog::record([
            'action_type' => 'api_token_revoked',
            'affected_table' => 'api_tokens',
            'affected_record_id' => $tokenId,
            'description' => 'Usuário revogou token de API.',
        ]);

        flash('success', 'Token revogado com sucesso.');
        redirect('/?route=settings.index');
    }

    public static function cancelTwoFactorSetup(): void
    {
        require_auth();
        verify_csrf();

        unset($_SESSION['two_factor_setup_secret']);
        flash('success', 'Configuração do 2FA cancelada.');
        redirect('/?route=settings.index');
    }

    public static function sendTwoFactorTestEmail(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        if (empty($user['two_factor_enabled'])) {
            flash('danger', 'Ative o 2FA antes de testar o envio por e-mail.');
            redirect('/?route=settings.index');
        }

        $code = EmailCode::generate();
        if (!EmailCode::sendSettingsTestCode($user, $code)) {
            flash('danger', 'Não foi possível enviar o código por e-mail. Verifique a configuração de e-mail do servidor.');
            redirect('/?route=settings.index');
        }

        AuditLog::record([
            'action_type' => 'user_2fa_email_test_sent',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário testou envio de código 2FA por e-mail.',
        ]);

        flash('success', 'Código de teste enviado para seu e-mail.');
        redirect('/?route=settings.index');
    }

    public static function enableTwoFactor(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        $secret = $_SESSION['two_factor_setup_secret'] ?? null;
        $password = (string) ($_POST['password'] ?? '');
        $code = (string) ($_POST['two_factor_code'] ?? '');

        if (!$user || !is_string($secret) || $secret === '') {
            flash('danger', 'Inicie a configuração do 2FA antes de ativar.');
            redirect('/?route=settings.index');
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            flash('danger', 'Senha atual inválida.');
            redirect('/?route=settings.index');
        }

        if (!TwoFactorAuth::verify($secret, $code)) {
            flash('danger', 'Código 2FA inválido.');
            redirect('/?route=settings.index');
        }

        User::enableTwoFactor((int) $user['id'], $secret);
        unset($_SESSION['two_factor_setup_secret']);
        $_SESSION['user']['two_factor_enabled'] = 1;

        AuditLog::record([
            'action_type' => 'user_2fa_enabled',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário ativou autenticação em dois fatores.',
        ]);

        flash('success', '2FA ativado com sucesso.');
        redirect('/?route=settings.index');
    }

    public static function disableTwoFactor(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        $password = (string) ($_POST['password'] ?? '');
        $code = (string) ($_POST['two_factor_code'] ?? '');

        if (!$user || empty($user['two_factor_enabled'])) {
            flash('danger', '2FA não está ativo nesta conta.');
            redirect('/?route=settings.index');
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            flash('danger', 'Senha atual inválida.');
            redirect('/?route=settings.index');
        }

        $secret = User::twoFactorSecret($user);
        if (!$secret || !TwoFactorAuth::verify($secret, $code)) {
            flash('danger', 'Código 2FA inválido.');
            redirect('/?route=settings.index');
        }

        User::disableTwoFactor((int) $user['id']);
        unset($_SESSION['two_factor_setup_secret']);
        $_SESSION['user']['two_factor_enabled'] = 0;

        AuditLog::record([
            'action_type' => 'user_2fa_disabled',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário desativou autenticação em dois fatores.',
        ]);

        flash('success', '2FA desativado com sucesso.');
        redirect('/?route=settings.index');
    }

    private static function activeSessions(array $user): array
    {
        if (empty($user['active_session_token'])) {
            return [];
        }

        return [[
            'started_at' => $user['active_session_started_at'] ?? null,
            'ip_address' => $user['active_session_ip'] ?? client_ip(),
            'user_agent' => $user['active_session_user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'current' => true,
        ]];
    }

    private static function consumeGeneratedApiToken(): ?array
    {
        $token = $_SESSION['generated_api_token'] ?? null;
        unset($_SESSION['generated_api_token']);

        return is_array($token) ? $token : null;
    }
}
