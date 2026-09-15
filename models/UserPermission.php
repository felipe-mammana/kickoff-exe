<?php
declare(strict_types=1);

class UserPermission
{
    private static bool $ready = false;
    public static function catalog(): array
    {
        return [
            'companies' => ['label' => 'Empresas', 'actions' => ['view' => 'Visualizar', 'create' => 'Criar', 'edit' => 'Editar', 'delete' => 'Excluir']],
            'machines' => ['label' => 'Dispositivos', 'actions' => ['view' => 'Visualizar', 'create' => 'Criar', 'edit' => 'Editar', 'delete' => 'Excluir', 'reveal' => 'Revelar senhas']],
            'vault' => ['label' => 'Cofre', 'actions' => ['view' => 'Acessar', 'create' => 'Criar', 'edit' => 'Editar', 'delete' => 'Excluir', 'reveal' => 'Revelar senhas', 'configure' => 'Categorias e configuracoes']],
            'reports' => ['label' => 'Relatorios', 'actions' => ['export' => 'Exportar']],
            'audit' => ['label' => 'Auditoria', 'actions' => ['view' => 'Consultar', 'export' => 'Exportar']],
            'sessions' => ['label' => 'Sessoes', 'actions' => ['view' => 'Visualizar', 'terminate' => 'Encerrar']],
            'users' => ['label' => 'Usuarios', 'actions' => ['view' => 'Visualizar', 'create' => 'Criar', 'edit' => 'Editar e redefinir senha', 'permissions' => 'Gerenciar permissoes']],
            'settings' => ['label' => 'Administracao', 'actions' => ['manage' => 'Configuracoes do sistema', 'backup' => 'Exportar backups', 'restore' => 'Restaurar e limpar dados']],
        ];
    }
    public static function keys(): array
    {
        $keys = [];
        foreach (self::catalog() as $group => $data) foreach ($data['actions'] as $action => $label) $keys[] = "$group.$action";
        return $keys;
    }
    public static function ensureTable(): void
    {
        if (self::$ready) return;
        db()->exec('CREATE TABLE IF NOT EXISTS user_permission_profiles (user_id INT UNSIGNED PRIMARY KEY, permissions_json TEXT NOT NULL) ENGINE=InnoDB');
        self::$ready = true;
    }
    public static function custom(int $id): ?array
    {
        self::ensureTable();
        $stmt = db()->prepare('SELECT permissions_json FROM user_permission_profiles WHERE user_id = ?');
        $stmt->execute([$id]);
        $json = $stmt->fetchColumn();
        return $json === false ? null : (json_decode($json, true) ?: []);
    }
    public static function defaults(string $role, bool $vault = false): array
    {
        $keys = ['companies.view', 'machines.view', 'machines.reveal', 'reports.export'];
        if (in_array($role, ['admin', 'editor'], true)) $keys = array_merge($keys, ['companies.create', 'companies.edit', 'machines.create', 'machines.edit']);
        if ($role === 'admin') $keys = array_merge($keys, ['companies.delete', 'machines.delete', 'audit.view', 'audit.export', 'users.view', 'users.create', 'users.edit', 'users.permissions', 'settings.manage', 'settings.backup', 'settings.restore']);
        if ($vault) $keys = array_merge($keys, ['vault.view', 'vault.create', 'vault.edit', 'vault.delete', 'vault.reveal', 'vault.configure', 'sessions.view', 'sessions.terminate']);
        return $keys;
    }
    public static function effective(array $user): array
    {
        return self::custom((int) $user['id']) ?? self::defaults(User::roleFromUser($user), (int) $user['id'] === (int) AppSetting::get('vault_exclusive_user_id', '0'));
    }
    public static function allows(?array $user, string $key): bool
    {
        return $user !== null && in_array($key, self::effective($user), true);
    }
    public static function save(int $id, array $keys): void
    {
        if (array_diff($keys, self::keys())) throw new InvalidArgumentException('Permissao desconhecida.');
        self::ensureTable();
        $stmt = db()->prepare('INSERT INTO user_permission_profiles (user_id, permissions_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json)');
        $stmt->execute([$id, json_encode(array_values(array_unique($keys)), JSON_THROW_ON_ERROR)]);
    }
    public static function routeKey(string $route): ?string
    {
        $exact = ['dashboard' => 'companies.view', 'sessions.index' => 'sessions.view', 'sessions.terminate' => 'sessions.terminate', 'audit.index' => 'audit.view', 'settings.auditFiles' => 'audit.view',
            'settings.vault' => 'vault.configure', 'settings.vault.update' => 'vault.configure',
            'settings.maintenance' => 'settings.backup', 'maintenance.exportCleanDatabase' => 'settings.backup', 'maintenance.exportFullBackup' => 'settings.backup', 'maintenance.importDatabase' => 'settings.restore', 'maintenance.cleanupOrphans' => 'settings.restore',
            'users.index' => 'users.view', 'users.store' => 'users.create', 'users.update' => 'users.edit', 'users.resetPassword' => 'users.edit', 'users.setStatus' => 'users.edit',
            'vault.categories.store' => 'vault.configure'];
        if (isset($exact[$route])) return $exact[$route];
        if ($route === 'export.download') return ($_GET['type'] ?? '') === 'audit' ? 'audit.export' : 'reports.export';
        if (preg_match('/^settings\.(microsoft|devices|audit)(\.|$)/', $route)) return 'settings.manage';
        foreach (['companies', 'machines', 'vault'] as $group) {
            if (!str_starts_with($route, $group . '.')) continue;
            $action = substr($route, strlen($group) + 1);
            $map = ['index' => 'view', 'show' => 'view', 'create' => 'create', 'store' => 'create', 'edit' => 'edit', 'update' => 'edit', 'deactivate' => 'delete', 'destroy' => 'delete', 'reactivate' => 'delete', 'deletePhoto' => 'delete', 'photos.view' => 'view', 'revealCredential' => 'reveal', 'reveal' => 'reveal', 'attachments.store' => 'edit', 'attachments.download' => 'view', 'attachments.delete' => 'delete'];
            return isset($map[$action]) ? $group . '.' . $map[$action] : 'settings.manage';
        }
        return null;
    }
    public static function enforce(string $route): void
    {
        $key = self::routeKey($route);
        if ($key !== null) {
            require_auth();
            if (!self::allows(current_user(), $key)) {
                http_response_code(403); view('errors/403', ['title' => 'Acesso negado']); exit;
            }
        }
    }
}
