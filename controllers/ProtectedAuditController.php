<?php
declare(strict_types=1);

class ProtectedAuditController
{
    public static function index(): void
    {
        require_permission('audit.view');
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        $records = null;
        $error = '';
        $notice = '';
        $date = gmdate('Y-m-d');
        $requestedDate = trim((string) ($_GET['date'] ?? ''));
        if (ProtectedAuditFiles::isValidDate($requestedDate)) {
            $date = $requestedDate;
        }
        $files = [];
        $filesError = '';
        try {
            $files = ProtectedAuditFiles::availableFiles();
        } catch (Throwable) {
            $filesError = 'Não foi possível listar os arquivos de auditoria no momento.';
        }
        if (is_post()) {
            verify_csrf();
            $date = trim((string) ($_POST['date'] ?? $date));
            if (!ProtectedAuditFiles::isValidDate($date)) {
                $error = 'Selecione uma data válida.';
            } else {
                $user = User::find((int) current_user()['id']);
                if (!$user || !PasswordSecurity::confirm($user, (string) ($_POST['password'] ?? ''))) {
                    $error = 'Senha incorreta ou limite de tentativas atingido.';
                } else {
                    try {
                        AuditLog::record([
                            'action_type' => 'protected_audit_viewed',
                            'description' => 'Usuário autorizado consultou arquivo protegido de auditoria.',
                            'new_data' => ['date' => $date],
                            'required' => true,
                        ]);
                        $records = ProtectedAuditFiles::readRecords($date);
                        if (!$records) {
                            $notice = 'Nenhum registro protegido foi encontrado nesta data.';
                        }
                    } catch (Throwable) {
                        error_log('Protected audit consultation failed.');
                        $error = 'Não foi possível ler o arquivo selecionado. Tente novamente ou verifique o armazenamento de auditoria.';
                    }
                }
            }
        }
        view('settings/protected-audit', [
            'title' => 'Arquivos de auditoria',
            'records' => $records,
            'error' => $error,
            'notice' => $notice,
            'date' => $date,
            'files' => $files,
            'filesError' => $filesError,
        ]);
    }
}
