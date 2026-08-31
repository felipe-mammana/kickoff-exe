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

    public static function cancelTwoFactorSetup(): void
    {
        require_auth();
        verify_csrf();

        unset($_SESSION['two_factor_setup_secret']);
        flash('success', 'Configuração do 2FA cancelada.');
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
}
