<?php
declare(strict_types=1);

class AccountChallengeController
{
    private static ?string $approved = null;
    private const ACTIONS = ['password' => 'updatePassword', 'email' => 'updateProfile', 'disable-2fa' => 'disableTwoFactor', 'replace-totp' => 'enableTwoFactor', 'replace-email-2fa' => 'enableEmailTwoFactor', 'admin-reset' => 'resetPassword', 'admin-email' => 'update', 'admin-permissions' => 'update', 'admin-create' => 'store'];

    public static function requireProof(array $user, string $action): void
    {
        if (self::$approved === $action) {
            self::$approved = null;
            return;
        }
        $_SESSION['account_challenge'] = [
            'action' => $action, 'user_id' => (int) $user['id'],
            'fingerprint' => User::authenticationFingerprint($user), 'expires' => time() + 600,
            'payload' => CredentialCrypto::encrypt(json_encode($_POST, JSON_THROW_ON_ERROR)),
        ];
        redirect('/?route=account.challenge');
        exit;
    }

    public static function index(): void
    {
        require_auth();
        header('Cache-Control: no-store, private, max-age=0');
        $user = User::find((int) current_user()['id']);
        $pending = $_SESSION['account_challenge'] ?? null;
        if (!$user || !is_array($pending) || ($pending['user_id'] ?? 0) !== (int) $user['id']
            || ($pending['expires'] ?? 0) <= time()
            || !isset(self::ACTIONS[$pending['action'] ?? ''])
            || !hash_equals(User::authenticationFingerprint($user), $pending['fingerprint'] ?? '')) {
            unset($_SESSION['account_challenge']);
            flash('danger', 'Confirmacao expirada. Inicie a alteracao novamente.');
            redirect('/?route=settings.account');
            return;
        }
        $error = '';
        $message = '';
        $secret = !empty($user['two_factor_enabled']) ? User::twoFactorSecret($user) : null;
        $hasTotp = is_string($secret) && $secret !== '';
        if (is_post()) {
            verify_csrf();
            $operation = (string) ($_POST['operation'] ?? 'verify');
            if ($operation === 'cancel') {
                unset($_SESSION['account_challenge']);
                redirect('/?route=settings.account');
                return;
            }
            if ($operation === 'send') {
                $limit = SecurityRateLimit::hit('account-change-send', (int) $user['id'], 1, 60);
                if ($limit['allowed']) $limit = SecurityRateLimit::hit('account-change-send-window', (int) $user['id'], 5, 600);
                if (!$limit['allowed']) {
                    $error = 'Aguarde antes de solicitar outro codigo.';
                } else {
                    $code = EmailCode::generate();
                    if (EmailCode::sendAccountChangeCode($user, $code, $pending['action'])) {
                        $_SESSION['account_challenge']['code_hash'] = password_hash($code, PASSWORD_DEFAULT);
                        $message = 'Codigo enviado ao e-mail atual da conta.';
                    } else $error = 'Nao foi possivel enviar. A alteracao nao foi realizada.';
                }
            } else {
                $limit = SecurityRateLimit::hit('account-change-proof', (int) $user['id']);
                $code = trim((string) ($_POST['code'] ?? ''));
                $method = (string) ($_POST['method'] ?? 'email');
                $valid = false;
                if ($limit['allowed'] && preg_match('/^[0-9]{6}$/D', $code)) {
                    if ($method === 'email') $valid = password_verify($code, $pending['code_hash'] ?? '');
                    if ($method === 'totp' && $hasTotp) {
                        $valid = TwoFactorAuth::verify($secret, $code);
                        if ($valid) $valid = SecurityRateLimit::hit('account-totp-used-' . hash('sha256', $secret . $code), (int) $user['id'], 1, 120)['allowed'];
                    }
                }
                if ($valid) {
                    $payload = json_decode((string) CredentialCrypto::decrypt($pending['payload']), true, 512, JSON_THROW_ON_ERROR);
                    unset($_SESSION['account_challenge']);
                    AuditLog::record(['action_type' => 'account_change_verified', 'description' => 'Segunda confirmacao de alteracao de conta aprovada.', 'new_data' => ['action' => $pending['action'], 'method' => $method], 'required' => true]);
                    self::$approved = $pending['action'];
                    $_POST = $payload;
                    $handler = self::ACTIONS[$pending['action']];
                    if (str_starts_with($pending['action'], 'admin-')) UserController::$handler();
                    else SettingsController::$handler();
                    return;
                }
                AuditLog::record(['action_type' => 'account_change_verification_failed', 'description' => 'Segunda confirmacao de conta recusada.']);
                if (!$limit['allowed'] || $limit['remaining'] === 0) unset($_SESSION['account_challenge']);
                $error = 'Codigo invalido ou limite atingido. Nenhuma alteracao realizada.';
            }
        }
        view('settings/account-challenge', ['title' => 'Confirmar alteracao', 'hasTotp' => $hasTotp, 'error' => $error, 'message' => $message]);
    }
}
