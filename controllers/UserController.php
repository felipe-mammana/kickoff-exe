<?php

declare(strict_types=1);

class UserController
{
    public static function index(): void
    {
        require_admin();

        view('users/index', [
            'title' => 'Usuários',
            'users' => User::all(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public static function store(): void
    {
        require_admin();
        verify_csrf();

        [$data, $errors] = self::validatedData(true);

        if ($errors) {
            view('users/index', [
                'title' => 'Usuários',
                'users' => User::all(),
                'errors' => $errors,
                'old' => $data,
                'openModal' => 'create',
            ]);
            return;
        }

        $userId = User::create($data);
        AuditLog::record([
            'action_type' => 'user_created',
            'affected_table' => 'users',
            'affected_record_id' => $userId,
            'description' => 'Usuário cadastrado.',
            'new_data' => self::auditData($data),
        ]);

        flash('success', 'Usuário cadastrado com sucesso.');
        redirect('/?route=users.index');
    }

    public static function update(): void
    {
        require_admin();
        verify_csrf();

        $user = self::requireUser();
        [$data, $errors] = self::validatedData(false, $user);

        if (self::wouldRemoveLastAdmin($user, !empty($data['is_admin']), !empty($user['is_active']))) {
            $errors['is_admin'] = 'Mantenha ao menos um administrador ativo.';
        }

        if ($errors) {
            view('users/index', [
                'title' => 'Usuários',
                'users' => User::all(),
                'errors' => $errors,
                'old' => $data + ['id' => (int) $user['id']],
                'openModal' => 'edit',
            ]);
            return;
        }

        $changes = self::changedFields($user, $data);
        User::update((int) $user['id'], $data);

        if ($changes) {
            AuditLog::record([
                'action_type' => 'user_updated',
                'affected_table' => 'users',
                'affected_record_id' => (int) $user['id'],
                'description' => 'Usuário alterado.',
                'old_data' => $changes['old'],
                'new_data' => $changes['new'],
            ]);
        }

        if ((int) $user['id'] === (int) current_user()['id']) {
            $_SESSION['user']['name'] = $data['name'];
            $_SESSION['user']['email'] = $data['email'];
            $_SESSION['user']['is_admin'] = (int) $data['is_admin'];
        }

        flash('success', 'Usuário atualizado com sucesso.');
        redirect('/?route=users.index');
    }

    public static function resetPassword(): void
    {
        require_admin();
        verify_csrf();

        $user = self::requireUser();
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $errors = [];

        if (strlen($password) < 8) {
            $errors['password'] = 'Use no minimo 8 caracteres.';
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'] = 'As senhas não conferem.';
        }

        if ($errors) {
            view('users/index', [
                'title' => 'Usuários',
                'users' => User::all(),
                'errors' => $errors,
                'old' => [
                    'id' => (int) $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'is_admin' => (int) $user['is_admin'],
                ],
                'openModal' => 'password',
            ]);
            return;
        }

        User::updatePassword((int) $user['id'], $password);
        AuditLog::record([
            'action_type' => 'user_password_reset',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Senha de usuário redefinida.',
            'new_data' => [
                'user_id' => (int) $user['id'],
                'email' => $user['email'],
            ],
        ]);

        flash('success', 'Senha redefinida com sucesso.');
        redirect('/?route=users.index');
    }

    public static function setStatus(): void
    {
        require_admin();
        verify_csrf();

        $user = self::requireUser();
        $active = ($_POST['status'] ?? '') === 'active';

        if ((int) $user['id'] === (int) current_user()['id'] && !$active) {
            flash('danger', 'Você não pode desativar sua própria conta.');
            redirect('/?route=users.index');
        }

        if (self::wouldRemoveLastAdmin($user, !empty($user['is_admin']), $active)) {
            flash('danger', 'Mantenha ao menos um administrador ativo.');
            redirect('/?route=users.index');
        }

        User::setActive((int) $user['id'], $active);
        AuditLog::record([
            'action_type' => $active ? 'user_activated' : 'user_deactivated',
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => $active ? 'Usuário ativado.' : 'Usuário desativado.',
            'old_data' => ['Status' => !empty($user['is_active']) ? 'Ativo' : 'Inativo'],
            'new_data' => ['Status' => $active ? 'Ativo' : 'Inativo'],
        ]);

        flash('success', $active ? 'Usuário ativado.' : 'Usuário desativado.');
        redirect('/?route=users.index');
    }

    private static function requireUser(): array
    {
        $user = User::find((int) ($_POST['id'] ?? $_GET['id'] ?? 0));

        if (!$user) {
            http_response_code(404);
            view('errors/404', ['title' => 'Usuário não encontrado']);
            exit;
        }

        return $user;
    }

    private static function validatedData(bool $creating, ?array $current = null): array
    {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? ($current['name'] ?? ''))),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ($current['email'] ?? '')))),
            'is_admin' => isset($_POST['is_admin']) ? 1 : 0,
            'is_active' => $creating ? 1 : (int) ($current['is_active'] ?? 1),
        ];

        if ($creating) {
            $data['password'] = (string) ($_POST['password'] ?? '');
            $data['password_confirmation'] = (string) ($_POST['password_confirmation'] ?? '');
        }

        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Campo obrigatório.';
        } elseif (strlen($data['name']) > 120) {
            $errors['name'] = 'Deve ter no máximo 120 caracteres.';
        }

        if ($data['email'] === '') {
            $errors['email'] = 'Campo obrigatório.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Informe um e-mail válido.';
        } elseif (strlen($data['email']) > 160) {
            $errors['email'] = 'Deve ter no máximo 160 caracteres.';
        } elseif (User::duplicateEmailExists($data['email'], $current ? (int) $current['id'] : null)) {
            $errors['email'] = 'Já existe um usuário com este e-mail.';
        }

        if ($creating) {
            if (strlen((string) $data['password']) < 8) {
                $errors['password'] = 'Use no minimo 8 caracteres.';
            } elseif ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'As senhas não conferem.';
            }
        }

        return [$data, $errors];
    }

    private static function wouldRemoveLastAdmin(array $user, bool $willBeAdmin, bool $willBeActive): bool
    {
        if (empty($user['is_admin']) || $willBeAdmin && $willBeActive) {
            return false;
        }

        return User::activeAdminCount((int) $user['id']) === 0;
    }

    private static function changedFields(array $old, array $new): array
    {
        $labels = [
            'name' => 'Nome',
            'email' => 'E-mail',
            'is_admin' => 'Administrador',
        ];
        $changes = ['old' => [], 'new' => []];

        foreach ($labels as $field => $label) {
            $oldValue = (string) ($old[$field] ?? '');
            $newValue = (string) ($new[$field] ?? '');

            if ($oldValue !== $newValue) {
                $changes['old'][$label] = $field === 'is_admin' ? ($oldValue === '1' ? 'Sim' : 'Não') : $oldValue;
                $changes['new'][$label] = $field === 'is_admin' ? ($newValue === '1' ? 'Sim' : 'Não') : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }

    private static function auditData(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => !empty($data['is_admin']),
            'is_active' => !empty($data['is_active']),
        ];
    }
}
