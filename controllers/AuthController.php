<?php

declare(strict_types=1);

class AuthController
{
    public static function login(): void
    {
        if (current_user()) {
            redirect('/');
        }

        if (is_post()) {
            verify_csrf();
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $user = User::findByEmail($email);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user'] = [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'is_admin' => (int) ($user['is_admin'] ?? 0),
                ];
                AuditLog::record([
                    'action_type' => 'login_success',
                    'affected_table' => 'users',
                    'affected_record_id' => (int) $user['id'],
                    'description' => 'Usuario entrou no sistema.',
                ]);
                redirect('/');
            }

            AuditLog::record([
                'user_id' => $user['id'] ?? null,
                'user_name' => $user['name'] ?? null,
                'user_email' => $email,
                'action_type' => 'login_failed',
                'affected_table' => 'users',
                'affected_record_id' => $user['id'] ?? null,
                'description' => 'Tentativa de login com dados incorretos.',
                'new_data' => ['email' => $email],
            ]);
            flash('danger', 'E-mail ou senha invalidos.');
        }

        view('auth/login', ['title' => 'Login']);
    }

    public static function logout(): void
    {
        verify_csrf();
        AuditLog::record([
            'action_type' => 'logout',
            'affected_table' => 'users',
            'affected_record_id' => current_user()['id'] ?? null,
            'description' => 'Usuario saiu do sistema.',
        ]);
        $_SESSION = [];
        session_destroy();
        redirect('/?route=login');
    }
}
