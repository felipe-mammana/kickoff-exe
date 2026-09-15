<?php
declare(strict_types=1);

final class MicrosoftMailController
{
    public static function dispatch(string $action): void
    {
        require_admin();
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        if ($action === 'index') {
            view('settings/microsoft', ['title' => 'Email Microsoft']);
            return;
        }
        if ($action !== 'callback') {
            if (!is_post()) {
                http_response_code(405);
                return;
            }
            verify_csrf();
        }
        try {
            if ($action === 'connect') {
                header('Location: ' . MicrosoftMail::authorize(), true, 303);
                exit;
            } elseif ($action === 'callback') {
                MicrosoftMail::callback();
                flash('success', 'Conta Microsoft conectada.');
            } elseif ($action === 'disconnect') {
                MicrosoftMail::disconnect();
                flash('success', 'Conexao removida deste servidor.');
            } elseif ($action === 'test') {
                $limit = SecurityRateLimit::hit('microsoft-mail-test', (int) current_user()['id'], 1, 60);
                if (!$limit['allowed']) {
                    throw new RuntimeException('Aguarde um minuto antes de testar novamente.');
                }
                if (!MicrosoftMail::send(MicrosoftMail::config()['sender'], 'EXE Kickoff - teste', 'Conexao Microsoft funcionando.')) {
                    throw new RuntimeException('Envio falhou. Verifique a configuracao ou reconecte.');
                }
                flash('success', 'Microsoft aceitou o envio. Confira a caixa do remetente.');
            }
        } catch (Throwable $error) {
            flash('danger', get_class($error) === RuntimeException::class ? $error->getMessage() : 'Nao foi possivel conectar. Verifique a configuracao.');
        }
        redirect('/?route=settings.microsoft.index');
    }
}
