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

        if ((int) $user['id'] === (int) current_user()['id'] && strcasecmp($data['email'], (string) $user['email']) !== 0) {
            flash('danger', 'Para trocar seu próprio e-mail, confirme a alteração nas configurações da conta.');
            redirect('/?route=settings.account');
        }

        if (self::wouldRemoveLastAdmin($user, $data['role'] === 'admin', !empty($user['is_active']))) {
            $errors['role'] = 'Mantenha ao menos um administrador ativo.';
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
        if (strcasecmp((string) $user['email'], $data['email']) !== 0) AccountChallengeController::requireProof(User::find((int) current_user()['id']), 'admin-email');
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
            $_SESSION['user']['role'] = $data['role'];
            $_SESSION['user']['is_admin'] = $data['role'] === 'admin' ? 1 : 0;
        }

        flash('success', 'Usuário atualizado com sucesso.');
        redirect('/?route=users.index');
    }

    public static function resetPassword(): void
    {
        require_admin();
        verify_csrf();

        $user = self::requireUser();
        if ((int) $user['id'] === (int) current_user()['id']) {
            flash('danger', 'Altere sua própria senha nas configurações da conta, confirmando a senha atual.');
            redirect('/?route=settings.account');
        }
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');
        $errors = [];

        if (!PasswordSecurity::valid($password)) {
            $errors['password'] = PasswordSecurity::REQUIREMENTS;
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
                    'role' => User::roleFromUser($user),
                    'is_admin' => (int) $user['is_admin'],
                ],
                'openModal' => 'password',
            ]);
            return;
        }

        $actor = current_user();
        AccountChallengeController::requireProof(User::find((int) $actor['id']), 'admin-reset');
        User::updatePassword((int) $user['id'], $password);
        AuditLog::record([
            'action_type' => 'user_password_reset',
            'user_id' => (int) $actor['id'], 'user_name' => $actor['name'], 'user_email' => $actor['email'],
            'affected_table' => 'users',
            'affected_record_id' => (int) $user['id'],
            'description' => 'Senha de usuário redefinida.',
            'new_data' => [
                'user_id' => (int) $user['id'],
                'email' => $user['email'],
            ],
        ]);

        flash('success', 'Senha redefinida e sessões do usuário encerradas.');
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
            'role' => User::normalizeRole((string) ($_POST['role'] ?? ($current ? User::roleFromUser($current) : 'viewer'))),
            'is_active' => $creating ? 1 : (int) ($current['is_active'] ?? 1),
        ];
        $data['is_admin'] = $data['role'] === 'admin' ? 1 : 0;

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
            if (!PasswordSecurity::valid((string) $data['password'])) {
                $errors['password'] = PasswordSecurity::REQUIREMENTS;
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
            'role' => 'Nível de acesso',
        ];
        $changes = ['old' => [], 'new' => []];

        foreach ($labels as $field => $label) {
            $oldValue = (string) ($old[$field] ?? '');
            $newValue = (string) ($new[$field] ?? '');

            if ($oldValue !== $newValue) {
                $changes['old'][$label] = $field === 'role' ? User::roleLabel($oldValue) : $oldValue;
                $changes['new'][$label] = $field === 'role' ? User::roleLabel($newValue) : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }

    private static function auditData(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => User::roleLabel($data['role']),
            'is_admin' => !empty($data['is_admin']),
            'is_active' => !empty($data['is_active']),
        ];
    }
}
