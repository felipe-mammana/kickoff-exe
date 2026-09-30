<?php

declare(strict_types=1);

class EmailCode
{
    public static function sendAccountChangeCode(array $user, string $code, string $action): bool
    {
        $label = ['password' => 'trocar sua senha', 'email' => 'trocar seu e-mail', 'disable-2fa' => 'desativar o 2FA', 'admin-permissions' => 'alterar permissoes de um usuario', 'admin-create' => 'criar um usuario', 'admin-email' => 'alterar o e-mail de um usuario', 'admin-reset' => 'redefinir a senha de um usuario'][$action] ?? 'alterar sua conta';
        return self::sendMail((string) $user['email'], APP_NAME . ' - confirmar alteracao de seguranca',
            'Codigo para ' . $label . ': ' . $code . '. Expira em ate 10 minutos. Nao compartilhe este codigo.',
            CodeEmailTemplate::render('Confirme a alteração', $code, 'Use este código para ' . $label . '. Se não solicitou, não autorize.', 'Expira em até 10 minutos.'));
    }
    public static function generate(): string
    {
        return (string) random_int(100000, 999999);
    }

    public static function sendLoginCode(array $user, string $code, int $validForSeconds = 600): bool
    {
        $email = trim((string) ($user['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = APP_NAME . ' - código de acesso';
        $message = implode("\n", [
            'Seu código de verificação é: ' . $code,
            '',
            'Ele expira em ' . (int) ceil($validForSeconds / 60) . ' minuto(s).',
            'Se você não tentou acessar o sistema, ignore este e-mail.',
        ]);
        return self::sendMail($email, $subject, $message, CodeEmailTemplate::render(
            'Seu código de acesso', $code, 'Use este código para confirmar seu acesso ao EXE Kickoff.',
            'Válido por ' . (int) ceil($validForSeconds / 60) . ' minuto(s).'
        ));
    }

    public static function sendSettingsTestCode(array $user, string $code): bool
    {
        $email = trim((string) ($user['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = APP_NAME . ' - teste de código por e-mail';
        $message = implode("\n", [
            'Seu código de teste é: ' . $code,
            '',
            'Este envio confirma que sua conta consegue receber códigos 2FA por e-mail.',
            'Para entrar no sistema, use o botão de e-mail na tela de verificação 2FA.',
        ]);
        return self::sendMail($email, $subject, $message, CodeEmailTemplate::render(
            'Confirme seu e-mail', $code, 'Use este código no sistema para configurar a autenticação por e-mail.',
            'Código exclusivo para esta solicitação.'
        ));
    }

    public static function sendEmailChangeCode(string $email, string $code, int $validForSeconds): bool
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return self::sendMail($email, APP_NAME . ' - confirmar novo e-mail', implode("\n", [
            'Seu código para confirmar o novo e-mail da conta é: ' . $code,
            '',
            'Ele expira em ' . (int) ceil($validForSeconds / 60) . ' minuto(s).',
            'O endereço atual permanece ativo até a confirmação.',
            'Se você não solicitou esta alteração, ignore este e-mail.',
        ]), CodeEmailTemplate::render('Confirme seu novo e-mail', $code,
            'Seu endereço atual permanece ativo até você confirmar esta alteração no EXE Kickoff.',
            'Válido por ' . (int) ceil($validForSeconds / 60) . ' minuto(s).'));
    }

    private static function sendMail(string $to, string $subject, string $message, ?string $html = null): bool
    {
        $message .= "\n\nNão compartilhe este código com ninguém, nem com quem se apresente como suporte da EXE.";
        if (MicrosoftMail::connected()) {
            return MicrosoftMail::send($to, $subject, $html ?? $message, $html !== null);
        }
        if (defined('SMTP_HOST') && SMTP_HOST !== '') {
            return self::sendSmtp($to, $subject, $message);
        }

        $headers = [
            'From: ' . self::formatAddress(self::fromAddress(), self::fromName()),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        return mail($to, self::encodeHeader($subject), $message, implode("\r\n", $headers));
    }

    private static function sendSmtp(string $to, string $subject, string $message): bool
    {
        $host = SMTP_HOST;
        $port = SMTP_PORT > 0 ? SMTP_PORT : 587;
        $encryption = SMTP_ENCRYPTION;
        $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            error_log('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
            return false;
        }

        stream_set_timeout($socket, 15);
        $ok = self::expect($socket, [220])
            && self::command($socket, 'EHLO ' . self::smtpLocalHost(), [250]);

        if ($ok && $encryption === 'tls') {
            $ok = self::command($socket, 'STARTTLS', [220])
                && stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
                && self::command($socket, 'EHLO ' . self::smtpLocalHost(), [250]);
        }

        if ($ok && SMTP_USERNAME !== '') {
            $ok = self::command($socket, 'AUTH LOGIN', [334])
                && self::command($socket, base64_encode(SMTP_USERNAME), [334])
                && self::command($socket, base64_encode(SMTP_PASSWORD), [235]);
        }

        $from = self::fromAddress();
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . self::formatAddress($from, self::fromName()),
            'To: ' . self::formatAddress($to, ''),
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        $body = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\r"], "\n", $message);
        $body = str_replace("\n", "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);

        $ok = $ok
            && self::command($socket, 'MAIL FROM:<' . $from . '>', [250])
            && self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251])
            && self::command($socket, 'DATA', [354])
            && self::command($socket, $body . "\r\n.", [250]);

        self::command($socket, 'QUIT', [221]);
        fclose($socket);

        return $ok;
    }

    private static function command($socket, string $command, array $expectedCodes): bool
    {
        fwrite($socket, $command . "\r\n");

        return self::expect($socket, $expectedCodes);
    }

    private static function expect($socket, array $expectedCodes): bool
    {
        $line = '';
        do {
            $response = fgets($socket, 515);
            if ($response === false) {
                return false;
            }
            $line = $response;
        } while (isset($response[3]) && $response[3] === '-');

        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            error_log('SMTP unexpected response: ' . trim($line));
            return false;
        }

        return true;
    }

    private static function formatAddress(string $email, string $name): string
    {
        if ($name === '') {
            return '<' . $email . '>';
        }

        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function fromName(): string
    {
        return defined('MAIL_FROM_NAME') && trim(MAIL_FROM_NAME) !== '' ? trim(MAIL_FROM_NAME) : APP_NAME;
    }

    private static function smtpLocalHost(): string
    {
        $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';

        return preg_replace('/[^A-Za-z0-9.-]/', '', (string) $host) ?: 'localhost';
    }

    private static function fromAddress(): string
    {
        if (defined('MAIL_FROM') && filter_var(MAIL_FROM, FILTER_VALIDATE_EMAIL)) {
            return MAIL_FROM;
        }

        $host = parse_url(APP_URL, PHP_URL_HOST) ?: 'localhost';
        $host = preg_replace('/[^A-Za-z0-9.-]/', '', (string) $host) ?: 'localhost';

        return 'no-reply@' . $host;
    }
}
