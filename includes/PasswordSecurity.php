<?php

declare(strict_types=1);

class PasswordSecurity
{
    public const REQUIREMENTS = 'Use no minimo 8 caracteres, com maiuscula, minuscula, numero e especial. Limite: 72 bytes.';

    public static function valid(string $password): bool
    {
        return strlen($password) <= 72 && preg_match('/^.{8,}$/us', $password) === 1
            && preg_match('/\p{Lu}/u', $password) === 1
            && preg_match('/\p{Ll}/u', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1;
    }

    public static function algorithm()
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function options(): array
    {
        return defined('PASSWORD_ARGON2ID')
            ? ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1]
            : ['cost' => 12];
    }

    public static function hash(string $password): string
    {
        return password_hash($password, self::algorithm(), self::options());
    }

    public static function confirm(array $user, string $password): bool
    {
        $scope = 'reauth:' . (int) $user['id'];
        $ip = client_ip();
        if (LoginAttempt::isBlocked($scope, $ip)) {
            self::event($user, 'password_confirmation_blocked');
            return false;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            LoginAttempt::recordFailure($scope, $ip);
            self::event($user, 'password_confirmation_failed');
            return false;
        }
        LoginAttempt::clear($scope, $ip);
        return true;
    }

    private static function event(array $user, string $action): void
    {
        AuditLog::record([
            'action_type' => $action, 'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Confirmacao de senha recusada por falha ou limite de seguranca.',
        ]);
    }
}
