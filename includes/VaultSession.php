<?php
declare(strict_types=1);

class VaultSession
{
    public const IDLE_SECONDS = 1200;

    public static function guard(): void
    {
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        $now = time();
        $userId = (int) current_user()['id'];
        $last = (int) ($_SESSION['vault_last_access'] ?? 0);
        $locked = ($_SESSION['vault_user_id'] ?? null) !== $userId || $now - $last >= self::IDLE_SECONDS;
        if ($locked) {
            unset($_SESSION['vault_last_access'], $_SESSION['vault_user_id']);
            $error = '';
            if (is_post() && isset($_POST['vault_unlock'])) {
                verify_csrf();
                $user = User::find($userId);
                if ($user && PasswordSecurity::confirm($user, (string) ($_POST['password'] ?? ''))) {
                    $_SESSION['vault_last_access'] = $now;
                    $_SESSION['vault_user_id'] = $userId;
                    AuditLog::record(['action_type' => 'vault_unlocked', 'description' => 'Cofre desbloqueado.']);
                    redirect('/?route=vault.index');
                    exit;
                }
                $error = 'Senha incorreta ou limite de tentativas atingido.';
            }
            http_response_code(423);
            view('vault/locked', ['title' => 'Cofre bloqueado', 'error' => $error]);
            exit;
        }
        $_SESSION['vault_last_access'] = $now;
    }
}
