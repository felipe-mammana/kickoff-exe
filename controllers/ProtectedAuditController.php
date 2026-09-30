<?php
declare(strict_types=1);

class ProtectedAuditController
{
    public static function index(): void
    {
        require_permission('audit.view');
        header('Cache-Control: no-store, private, max-age=0');
        $text = null;
        $error = '';
        $date = gmdate('Y-m-d');
        if (is_post()) {
            verify_csrf();
            $date = (string) ($_POST['date'] ?? $date);
            $user = User::find((int) current_user()['id']);
            if (!$user || !PasswordSecurity::confirm($user, (string) ($_POST['password'] ?? ''))) {
                $error = 'Senha incorreta ou limite de tentativas atingido.';
            } else {
                AuditLog::record(['action_type' => 'protected_audit_viewed', 'description' => 'Administrador consultou arquivo protegido de auditoria.', 'required' => true]);
                $text = ProtectedAuditFiles::read($date);
            }
        }
        view('settings/protected-audit', ['title' => 'Arquivos de auditoria', 'text' => $text, 'error' => $error, 'date' => $date]);
    }
}
