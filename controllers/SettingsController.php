<?php

declare(strict_types=1);

class SettingsController
{
    public static function index(): void
    {
        self::render();
    }

    public static function account(): void
    {
        self::render('account');
    }

    public static function preferences(): void
    {
        self::render('preferences');
    }

    public static function twoFactor(): void
    {
        self::render('two_factor');
    }

    public static function sessionLimit(): void
    {
        self::render('session_limit');
    }

    public static function security(): void
    {
        self::render('security');
    }

    public static function maintenance(): void
    {
        self::render('maintenance');
    }

    public static function devices(): void
    {
        self::render('devices');
    }

    public static function vault(): void
    {
        require_vault_access();
        self::render('vault');
    }

    private static function render(?string $topic = null): void
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
        $emailSetupPending = !empty($_SESSION['two_factor_email_setup_code_hash'])
            && (int) ($_SESSION['two_factor_email_setup_code_expires_at'] ?? 0) >= time();

        view('settings/index', [
            'title' => 'Configurações',
            'settingsTopic' => $topic,
            'accountUser' => $user,
            'pendingEmailChange' => self::pendingEmailChange($user),
            'emailRetryAfter' => max(
                SecurityRateLimit::retryAfter('email-send', (int) $user['id'], 1),
                SecurityRateLimit::retryAfter('email-send-window', (int) $user['id'])
            ),
            'twoFactorAuthenticatorEnabled' => User::twoFactorSecret($user) !== null,
            'twoFactorEmailSetupPending' => $emailSetupPending,
            'twoFactorSetupSecret' => $setupSecret,
            'twoFactorProvisioningUri' => $setupSecret
                ? TwoFactorAuth::provisioningUri((string) $user['email'], APP_NAME, $setupSecret)
                : null,
            'activeSessions' => self::activeSessions($user),
            'recentAccesses' => AuditLog::latestAccountAccesses((int) $user['id']),
            'maintenanceStatus' => is_admin() ? DatabaseMaintenance::status() : null,
            'auditRetentionDays' => is_admin() ? AppSetting::auditRetentionDays() : null,
            'deviceSettings' => is_admin() ? AppSetting::deviceSettings() : null,
            'vaultSettings' => is_admin() ? AppSetting::vaultSettings() : null,
            'deviceTypes' => Machine::deviceTypes(),
            'deviceFieldLabels' => AppSetting::deviceFieldLabels(),
            'vaultCustomFieldTypeLabels' => AppSetting::vaultCustomFieldTypeLabels(),
            'photoMimeLabels' => [
                'image/jpeg' => 'JPG / JPEG',
                'image/png' => 'PNG',
                'image/webp' => 'WEBP',
            ],
        ]);
    }

    public static function audit(): void
    {
        self::render('audit');
    }

    public static function updateAuditSettings(): void
    {
        require_admin();
        verify_csrf();

        $days = (int) ($_POST['audit_retention_days'] ?? 365);
        if (!in_array($days, [30, 60, 90, 180, 365, 730, 1095], true)) {
            flash('danger', 'Retenção de logs inválida.');
            redirect('/?route=settings.audit');
        }

        AppSetting::set('audit_retention_days', (string) $days);
        AuditLog::record([
            'action_type' => 'audit_retention_updated',
            'affected_table' => 'app_settings',
            'description' => 'Retenção de logs de auditoria atualizada.',
            'new_data' => ['Dias' => $days],
        ]);

        flash('success', 'Retenção de logs salva com sucesso.');
        redirect('/?route=settings.audit');
    }

    public static function cleanupAuditLogs(): void
    {
        require_admin();
        verify_csrf();

        $days = AppSetting::auditRetentionDays();
        $deleted = AuditLog::deleteOlderThanDays($days);
        AuditLog::record([
            'action_type' => 'audit_logs_retention_cleaned',
            'affected_table' => 'audit_logs',
            'description' => 'Logs antigos removidos pela política de retenção.',
            'old_data' => [
                'Retenção em dias' => $days,
                'Logs removidos' => $deleted,
            ],
        ]);

        flash('success', $deleted . ' log(s) antigo(s) removido(s).');
        redirect('/?route=settings.audit');
    }

    public static function updateDeviceSettings(): void
    {
        require_admin();
        verify_csrf();

        $deviceTypes = Machine::deviceTypes();
        $allowedFields = array_keys(AppSetting::deviceFieldLabels());
        $currentDeviceSettings = AppSetting::deviceSettings();
        $prefixes = [];
        foreach ($deviceTypes as $type => $_label) {
            $prefix = strtoupper(trim(substr((string) ($_POST['label_prefixes'][$type] ?? ''), 0, 12)));
            $prefix = (string) preg_replace('/[^A-Z0-9_-]/', '', $prefix);
            $usesCompany = !empty($_POST['label_prefix_uses_company'][$type])
                || stripos((string) ($currentDeviceSettings['label_prefixes'][$type] ?? ''), '{empresa}') !== false;
            $prefixes[$type] = $usesCompany ? $prefix . '{empresa}' : $prefix;
        }

        $defaultCategories = array_values(array_intersect((array) ($_POST['default_categories'] ?? []), array_keys($deviceTypes)));
        if (!$defaultCategories) {
            $defaultCategories = array_keys($deviceTypes);
        }

        $requiredFields = [];
        foreach ($deviceTypes as $type => $_label) {
            $fields = array_values(array_intersect((array) ($_POST['required_fields'][$type] ?? []), $allowedFields));
            $requiredFields[$type] = $fields ?: ['tag'];
        }

        $photoMimes = array_values(array_intersect((array) ($_POST['photo_mimes'] ?? []), ALLOWED_IMAGE_MIMES));
        if (!$photoMimes) {
            $photoMimes = ALLOWED_IMAGE_MIMES;
        }

        $attachmentExtensions = array_values(array_intersect((array) ($_POST['attachment_extensions'] ?? []), AppSetting::defaultAttachmentExtensions()));
        if (!$attachmentExtensions) {
            $attachmentExtensions = AppSetting::defaultAttachmentExtensions();
        }

        $settings = [
            'label_prefixes' => $prefixes,
            'default_categories' => $defaultCategories,
            'required_fields' => $requiredFields,
            'photo_max_mb' => min(25, max(1, (int) ($_POST['photo_max_mb'] ?? 5))),
            'photo_mimes' => $photoMimes,
            'attachment_max_mb' => min(25, max(1, (int) ($_POST['attachment_max_mb'] ?? 25))),
            'attachment_extensions' => $attachmentExtensions,
        ];

        AppSetting::setJson('device_settings', $settings);
        AuditLog::record([
            'action_type' => 'device_settings_updated',
            'affected_table' => 'app_settings',
            'description' => 'Configurações de empresas e dispositivos atualizadas.',
            'new_data' => $settings,
        ]);

        flash('success', 'Configurações de empresas e dispositivos salvas.');
        redirect('/?route=settings.devices');
    }

    public static function updateVaultSettings(): void
    {
        require_vault_access();
        verify_csrf();

        $categories = AppSetting::normalizeVaultDefaultCategories((array) ($_POST['vault_categories'] ?? []));
        if (!$categories) {
            $categories = AppSetting::defaultVaultSettings()['default_categories'];
        }

        $days = (int) ($_POST['credential_expiration_days'] ?? 365);
        if (!in_array($days, [0, 30, 60, 90, 180, 365, 730, 1095], true)) {
            $days = 365;
        }

        $settings = [
            'default_categories' => $categories,
            'allow_password_copy' => !empty($_POST['allow_password_copy']),
            'require_reveal_confirmation' => !empty($_POST['require_reveal_confirmation']),
            'audit_secret_access' => !empty($_POST['audit_secret_access']),
            'credential_expiration_days' => $days,
        ];

        AppSetting::setJson('vault_settings', $settings);
        $created = self::ensureVaultDefaultCategories($categories);
        AuditLog::record([
            'action_type' => 'vault_settings_updated',
            'affected_table' => 'app_settings',
            'description' => 'Configurações do cofre de senhas atualizadas.',
            'new_data' => $settings + ['Categorias criadas' => $created],
        ]);

        flash('success', 'Configurações do cofre salvas.');
        redirect('/?route=settings.vault');
    }

    public static function prepareTwoFactor(): void
    {
        require_auth();
        verify_csrf();

        $_SESSION['two_factor_setup_secret'] = TwoFactorAuth::generateSecret();
        redirect('/?route=settings.twoFactor');
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
            redirect('/?route=settings.account');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
            flash('danger', 'Informe um e-mail válido.');
            redirect('/?route=settings.account');
        }

        if (User::duplicateEmailExists($email, (int) $user['id'])) {
            flash('danger', 'Este e-mail já está em uso por outro usuário.');
            redirect('/?route=settings.account');
        }

        if (strcasecmp($email, (string) $user['email']) !== 0) {
            self::requirePasswordConfirmation($user, (string) ($_POST['current_password'] ?? ''), 'settings.account');
            AccountChallengeController::requireProof($user, 'email');
            self::requireEmailSendAllowance($user, 'settings.account');
            self::sendEmailChangeCode($user, $name, $email);
            redirect('/?route=settings.account');
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
        redirect('/?route=settings.account');
    }

    public static function confirmEmailChange(): void
    {
        require_auth();
        verify_csrf();
        $user = User::find((int) current_user()['id']);
        $pending = $user ? self::pendingEmailChange($user) : null;
        if (!$pending) {
            flash('danger', 'Solicitação de troca de e-mail expirada. Inicie novamente.');
            redirect('/?route=settings.account');
        }

        self::requireVerificationAllowance($user, 'email-change', 'settings.account');
        $code = trim((string) ($_POST['email_change_code'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/D', $code) || !password_verify($code, $pending['code_hash'])) {
            flash('danger', 'Código inválido. Confira o código enviado ao novo e-mail.');
            redirect('/?route=settings.account');
        }
        if (User::duplicateEmailExists($pending['email'], (int) $user['id'])) {
            unset($_SESSION['pending_email_change']);
            flash('danger', 'Este e-mail já está em uso por outro usuário.');
            redirect('/?route=settings.account');
        }

        User::updateProfile((int) $user['id'], $pending['name'], $pending['email']);
        unset($_SESSION['pending_email_change'], $_SESSION['two_factor_email_setup_code_hash'], $_SESSION['two_factor_email_setup_code_expires_at']);
        SecurityRateLimit::clear('email-change', (int) $user['id']);
        $_SESSION['user']['name'] = $pending['name'];
        $_SESSION['user']['email'] = $pending['email'];
        AuditLog::record([
            'action_type' => 'user_email_changed',
            'affected_table' => 'users', 'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário confirmou a alteração do e-mail da conta.',
            'old_data' => ['email' => $user['email']],
            'new_data' => ['email' => $pending['email']],
        ]);
        flash('success', 'E-mail confirmado e perfil atualizado.');
        redirect('/?route=settings.account');
    }

    public static function resendEmailChange(): void
    {
        require_auth();
        verify_csrf();
        $user = User::find((int) current_user()['id']);
        $pending = $user ? self::pendingEmailChange($user) : null;
        if (!$pending) {
            flash('danger', 'Solicitação expirada. Inicie a troca de e-mail novamente.');
            redirect('/?route=settings.account');
        }
        self::requireEmailSendAllowance($user, 'settings.account');
        self::sendEmailChangeCode($user, $pending['name'], $pending['email'], (int) $pending['expires_at']);
        redirect('/?route=settings.account');
    }

    public static function cancelEmailChange(): void
    {
        require_auth();
        verify_csrf();
        unset($_SESSION['pending_email_change']);
        flash('success', 'Troca de e-mail cancelada.');
        redirect('/?route=settings.account');
    }

    private static function pendingEmailChange(array $user): ?array
    {
        $pending = $_SESSION['pending_email_change'] ?? null;
        if (!is_array($pending)
            || (int) ($pending['user_id'] ?? 0) !== (int) $user['id']
            || (int) ($pending['expires_at'] ?? 0) <= time()
            || !hash_equals(User::authenticationFingerprint($user), (string) ($pending['fingerprint'] ?? ''))
        ) {
            unset($_SESSION['pending_email_change']);
            return null;
        }
        return $pending;
    }

    private static function sendEmailChangeCode(array $user, string $name, string $email, ?int $expiresAt = null): void
    {
        $code = EmailCode::generate();
        $expiresAt = $expiresAt ?? time() + 600;
        if (!EmailCode::sendEmailChangeCode($email, $code, max(1, $expiresAt - time()))) {
            flash('danger', 'Não foi possível enviar o código. O e-mail da conta continua o mesmo. Tente novamente em um minuto.');
            return;
        }
        $_SESSION['pending_email_change'] = [
            'user_id' => (int) $user['id'], 'name' => $name, 'email' => $email,
            'fingerprint' => User::authenticationFingerprint($user),
            'code_hash' => password_hash($code, PASSWORD_DEFAULT), 'expires_at' => $expiresAt,
        ];
        AuditLog::record([
            'action_type' => 'user_email_change_requested',
            'affected_table' => 'users', 'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário solicitou verificação do novo e-mail.',
        ]);
        flash('success', 'Código enviado ao novo e-mail. Confirme para concluir a alteração.');
    }

    private static function requireVerificationAllowance(array $user, string $purpose, string $route): void
    {
        $limit = SecurityRateLimit::hit($purpose, (int) $user['id']);
        if (!$limit['allowed']) {
            flash('danger', 'Muitas tentativas. Aguarde ' . $limit['retry_after'] . ' segundos e tente novamente.');
            redirect('/?route=' . $route);
        }
    }

    private static function requirePasswordConfirmation(array $user, string $password, string $route): void
    {
        if (!PasswordSecurity::confirm($user, $password)) {
            AuditLog::record([
                'action_type' => 'user_password_confirmation_failed',
                'affected_table' => 'users', 'affected_record_id' => (int) $user['id'],
                'description' => 'Falha na confirmação de senha para alterar a segurança da conta.',
            ]);
            flash('danger', 'Confirme sua senha atual para concluir esta alteração.');
            redirect('/?route=' . $route);
        }
    }

    private static function requireEmailSendAllowance(array $user, string $route): void
    {
        $limit = SecurityRateLimit::emailSend((int) $user['id']);
        if (!$limit['allowed']) {
            flash('danger', 'Aguarde ' . $limit['retry_after'] . ' segundos antes de solicitar outro e-mail.');
            redirect('/?route=' . $route);
        }
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

        self::requirePasswordConfirmation($user, $currentPassword, 'settings.account');

        if (!PasswordSecurity::valid($password)) {
            flash('danger', PasswordSecurity::REQUIREMENTS);
            redirect('/?route=settings.account');
        }

        if ($password !== $confirmation) {
            flash('danger', 'A confirmação da nova senha não confere.');
            redirect('/?route=settings.account');
        }

        AccountChallengeController::requireProof($user, 'password');
        User::updatePassword((int) $user['id'], $password);

        AuditLog::record([
            'action_type' => 'user_password_changed',
            'user_id' => (int) $user['id'], 'user_name' => $user['name'], 'user_email' => $user['email'],
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário alterou a própria senha.',
        ]);

        $_SESSION = [];
        session_regenerate_id(true);
        flash('success', 'Senha alterada. As sessões anteriores foram encerradas. Entre com a nova senha.');
        redirect('/?route=login');
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
            redirect('/?route=settings.preferences');
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
        redirect('/?route=settings.preferences');
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
            redirect('/?route=settings.security');
        }

        $requirePassword = can_access_vault()
            ? !empty($_POST['vault_require_password_reveal'])
            : !empty($user['vault_require_password_reveal']);
        if (!empty($user['vault_require_password_reveal']) && !$requirePassword) {
            self::requirePasswordConfirmation($user, (string) ($_POST['current_password'] ?? ''), 'settings.security');
        }
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
        redirect('/?route=settings.security');
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
        redirect('/?route=settings.security');
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
        unset($_SESSION['two_factor_email_setup_code_hash'], $_SESSION['two_factor_email_setup_code_expires_at']);
        flash('success', 'Configuração do 2FA cancelada.');
        redirect('/?route=settings.twoFactor');
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
            redirect('/?route=settings.twoFactor');
        }

        self::requireEmailSendAllowance($user, 'settings.twoFactor');
        $code = EmailCode::generate();
        if (!EmailCode::sendSettingsTestCode($user, $code)) {
            flash('danger', 'Não foi possível enviar o código por e-mail. Verifique a configuração de e-mail do servidor.');
            redirect('/?route=settings.twoFactor');
        }

        AuditLog::record([
            'action_type' => 'user_2fa_email_test_sent',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário testou envio de código 2FA por e-mail.',
        ]);

        flash('success', 'Código de teste enviado para seu e-mail.');
        redirect('/?route=settings.twoFactor');
    }

    public static function prepareEmailTwoFactor(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        if (!$user) {
            redirect('/?route=login');
        }

        self::requireEmailSendAllowance($user, 'settings.twoFactor');
        $code = EmailCode::generate();
        $_SESSION['two_factor_email_setup_code_hash'] = password_hash($code, PASSWORD_DEFAULT);
        $_SESSION['two_factor_email_setup_code_expires_at'] = time() + 600;

        if (!EmailCode::sendSettingsTestCode($user, $code)) {
            unset($_SESSION['two_factor_email_setup_code_hash'], $_SESSION['two_factor_email_setup_code_expires_at']);
            flash('danger', 'Não foi possível enviar o código por e-mail. Verifique a configuração SMTP.');
            redirect('/?route=settings.twoFactor');
        }

        AuditLog::record([
            'action_type' => 'user_2fa_email_test_sent',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário solicitou código para ativar 2FA por e-mail.',
        ]);

        flash('success', 'Código de ativação enviado para seu e-mail.');
        redirect('/?route=settings.twoFactor');
    }

    public static function enableEmailTwoFactor(): void
    {
        require_auth();
        verify_csrf();

        $user = User::find((int) current_user()['id']);
        $password = (string) ($_POST['password'] ?? '');
        $code = preg_replace('/\s+/', '', (string) ($_POST['email_two_factor_code'] ?? '')) ?? '';
        $codeHash = $_SESSION['two_factor_email_setup_code_hash'] ?? null;
        $expiresAt = (int) ($_SESSION['two_factor_email_setup_code_expires_at'] ?? 0);
        if (!$user) {
            redirect('/?route=login');
        }

        if (!is_string($codeHash) || $expiresAt < time()) {
            unset($_SESSION['two_factor_email_setup_code_hash'], $_SESSION['two_factor_email_setup_code_expires_at']);
            flash('danger', 'Código de ativação expirado. Solicite um novo código por e-mail.');
            redirect('/?route=settings.twoFactor');
        }

        self::requireVerificationAllowance($user, '2fa-settings', 'settings.twoFactor');
        if (!PasswordSecurity::confirm($user, $password)) {
            flash('danger', 'Senha atual inválida.');
            redirect('/?route=settings.twoFactor');
        }

        if (!password_verify($code, $codeHash)) {
            flash('danger', 'Código de e-mail inválido.');
            redirect('/?route=settings.twoFactor');
        }

        if (!empty($user['two_factor_enabled'])) AccountChallengeController::requireProof($user, 'replace-email-2fa');
        User::enableEmailTwoFactor((int) $user['id']);
        SecurityRateLimit::clear('2fa-settings', (int) $user['id']);
        unset($_SESSION['two_factor_setup_secret'], $_SESSION['two_factor_email_setup_code_hash'], $_SESSION['two_factor_email_setup_code_expires_at']);
        $_SESSION['user']['two_factor_enabled'] = 1;

        AuditLog::record([
            'action_type' => 'user_2fa_enabled',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário ativou 2FA por código de e-mail.',
        ]);

        flash('success', '2FA por e-mail ativado com sucesso.');
        redirect('/?route=settings.twoFactor');
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
            redirect('/?route=settings.twoFactor');
        }

        self::requireVerificationAllowance($user, '2fa-settings', 'settings.twoFactor');
        if (!PasswordSecurity::confirm($user, $password)) {
            flash('danger', 'Senha atual inválida.');
            redirect('/?route=settings.twoFactor');
        }

        if (!TwoFactorAuth::verify($secret, $code)) {
            flash('danger', 'Código 2FA inválido.');
            redirect('/?route=settings.twoFactor');
        }

        if (!empty($user['two_factor_enabled'])) AccountChallengeController::requireProof($user, 'replace-totp');
        User::enableTwoFactor((int) $user['id'], $secret);
        SecurityRateLimit::clear('2fa-settings', (int) $user['id']);
        unset($_SESSION['two_factor_setup_secret']);
        $_SESSION['user']['two_factor_enabled'] = 1;

        AuditLog::record([
            'action_type' => 'user_2fa_enabled',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário ativou autenticação em dois fatores.',
        ]);

        flash('success', '2FA ativado com sucesso.');
        redirect('/?route=settings.twoFactor');
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
            redirect('/?route=settings.twoFactor');
        }

        self::requireVerificationAllowance($user, '2fa-settings', 'settings.twoFactor');
        if (!PasswordSecurity::confirm($user, $password)) {
            flash('danger', 'Senha atual inválida.');
            redirect('/?route=settings.twoFactor');
        }

        AccountChallengeController::requireProof($user, 'disable-2fa');
        User::disableTwoFactor((int) $user['id']);
        SecurityRateLimit::clear('2fa-settings', (int) $user['id']);
        unset($_SESSION['two_factor_setup_secret']);
        $_SESSION['user']['two_factor_enabled'] = 0;

        AuditLog::record([
            'action_type' => 'user_2fa_disabled',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Usuário desativou autenticação em dois fatores.',
        ]);

        flash('success', '2FA desativado com sucesso.');
        redirect('/?route=settings.twoFactor');
    }

    private static function ensureVaultDefaultCategories(array $categories): int
    {
        $created = 0;
        $userId = (int) current_user()['id'];

        foreach ($categories as $category) {
            $parentId = self::ensureVaultCategoryNode($category, null, $userId, $created);
            foreach ((array) ($category['children'] ?? []) as $child) {
                self::ensureVaultCategoryNode($child, $parentId, $userId, $created);
            }
        }

        return $created;
    }

    private static function ensureVaultCategoryNode(array $category, ?int $parentId, int $userId, int &$created): int
    {
        $name = (string) ($category['name'] ?? '');
        $icon = (string) ($category['icon'] ?? 'folder');
        $slugBase = self::vaultCategoryBaseSlug(($parentId ? 'sub-' . $parentId . '-' : '') . $name);
        $existing = VaultCategory::findGlobalBySlug($slugBase);

        if ($existing) {
            VaultCategory::updateDefault((int) $existing['id'], $name, $icon, $parentId);

            return (int) $existing['id'];
        }

        $id = VaultCategory::create([
            'company_id' => null,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => self::uniqueVaultCategorySlug($slugBase),
            'description' => $parentId ? 'Subcategoria padrão do cofre.' : 'Categoria padrão do cofre.',
            'icon' => $icon,
            'is_active' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $created++;

        return $id;
    }

    private static function vaultCategoryBaseSlug(string $name): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name), '-'));

        return $base !== '' ? substr($base, 0, 110) : 'categoria';
    }

    private static function uniqueVaultCategorySlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (VaultCategory::slugExists($slug)) {
            $slug = substr($base, 0, 104) . '-' . $suffix;
            $suffix++;
        }

        return $slug;
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
