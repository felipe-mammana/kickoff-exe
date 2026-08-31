<?php

declare(strict_types=1);

class EmailCode
{
    public static function generate(): string
    {
        return (string) random_int(100000, 999999);
    }

    public static function sendLoginCode(array $user, string $code): bool
    {
        $email = trim((string) ($user['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = APP_NAME . ' - código de acesso';
        $message = implode("\n", [
            'Seu código de verificação é: ' . $code,
            '',
            'Ele expira em 10 minutos.',
            'Se você não tentou acessar o sistema, ignore este e-mail.',
        ]);
        $headers = [
            'From: ' . self::fromAddress(),
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        return mail($email, $subject, $message, implode("\r\n", $headers));
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
