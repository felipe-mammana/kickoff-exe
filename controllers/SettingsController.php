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
