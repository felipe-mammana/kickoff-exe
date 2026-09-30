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
            'vault' => ['label' => 'Cofre', 'actions' => ['view' => 'Acessar', 'create' => 'Criar', 'edit' => 'Editar', 'delete' => 'Excluir', 'reveal' => 'Revelar senhas', 'copy' => 'Copiar senhas', 'export' => 'Exportar credenciais', 'configure' => 'Categorias e configuracoes']],
            'reports' => ['label' => 'Relatorios', 'actions' => ['view' => 'Visualizar', 'export' => 'Exportar']],
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
    public static function different(array $before, array $after): bool
    {
        return (bool) (array_diff($before, $after) || array_diff($after, $before));
    }
    public static function ensureTable(): void
    {
        if (self::$ready) return;
        db()->exec('CREATE TABLE IF NOT EXISTS user_permission_profiles (user_id INT UNSIGNED PRIMARY KEY, permissions_json TEXT NOT NULL, delegation_json TEXT NULL) ENGINE=InnoDB');
        if (!db()->query("SHOW COLUMNS FROM user_permission_profiles LIKE 'delegation_json'")->fetch()) db()->exec('ALTER TABLE user_permission_profiles ADD delegation_json TEXT NULL');
        self::$ready = true;
    }
    public static function custom(int $id): ?array
    {
        self::ensureTable();
        $stmt = db()->prepare('SELECT permissions_json FROM user_permission_profiles WHERE user_id = ?');
        $stmt->execute([$id]);
        $json = $stmt->fetchColumn();
        if ($json === false) return null;
        $decoded = json_decode((string) $json, true);
        return is_array($decoded) ? array_values(array_intersect(array_filter($decoded, 'is_string'), self::keys())) : [];
    }
    public static function defaults(string $role, bool $vault = false): array
    {
        $keys = ['companies.view', 'machines.view', 'machines.reveal', 'reports.view', 'reports.export'];
        if (in_array($role, ['admin', 'editor'], true)) $keys = array_merge($keys, ['companies.create', 'companies.edit', 'machines.create', 'machines.edit']);
        if ($role === 'admin') $keys = array_merge($keys, ['companies.delete', 'machines.delete', 'audit.view', 'audit.export', 'users.view', 'users.create', 'users.edit', 'users.permissions', 'settings.manage', 'settings.backup', 'settings.restore']);
        if ($vault) $keys = array_merge($keys, ['vault.view', 'vault.create', 'vault.edit', 'vault.delete', 'vault.reveal', 'vault.copy', 'vault.configure', 'sessions.view', 'sessions.terminate']);
        return $keys;
    }
    public static function effective(array $user): array
    {
        return self::custom((int) $user['id']) ?? self::defaults(User::roleFromUser($user), (int) $user['id'] === (int) AppSetting::get('vault_exclusive_user_id', '0'));
    }
    public static function allows(?array $user, string $key): bool
    {
        if ($user === null) return false;
        $keys = self::effective($user);
        if (!in_array($key, $keys, true)) return false;
        [$group, $action] = explode('.', $key, 2);
        if ($group !== 'settings' && $action !== 'view' && !in_array($group . '.view', $keys, true)) return false;
        return !in_array($key, ['vault.copy', 'vault.export'], true) || in_array('vault.reveal', $keys, true);
    }
    public static function delegation(array $user): array
    {
        self::ensureTable();
        $stmt = db()->prepare('SELECT delegation_json FROM user_permission_profiles WHERE user_id = ?');
        $stmt->execute([(int) $user['id']]);
        $json = $stmt->fetchColumn();
        if ($json === false) return User::roleFromUser($user) === 'admin' ? self::keys() : [];
        $decoded = $json ? json_decode($json, true) : [];
        return is_array($decoded) ? array_values(array_intersect(array_filter($decoded, 'is_string'), self::keys())) : [];
    }
    public static function save(int $id, array $keys, ?array $delegation = null): void
    {
        if (array_diff($keys, self::keys())) throw new InvalidArgumentException('Permissao desconhecida.');
        self::ensureTable();
        if ($delegation === null) $delegation = self::delegation(User::find($id));
        if (array_diff($delegation, self::keys())) throw new InvalidArgumentException('Delegacao desconhecida.');
        $stmt = db()->prepare('INSERT INTO user_permission_profiles (user_id, permissions_json, delegation_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json), delegation_json = VALUES(delegation_json)');
        $stmt->execute([$id, json_encode(array_values(array_unique($keys)), JSON_THROW_ON_ERROR), json_encode($delegation, JSON_THROW_ON_ERROR)]);
    }
    public static function routeKey(string $route): ?string
    {
        if (in_array($route, ['dashboard', 'users.update', 'settings.maintenance'], true)) return null;
        $exact = ['dashboard' => 'reports.view', 'reports.index' => 'reports.view', 'vault.export' => 'vault.export', 'sessions.index' => 'sessions.view', 'sessions.terminate' => 'sessions.terminate', 'audit.index' => 'audit.view', 'settings.auditFiles' => 'audit.view',
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
    public static function migrateLegacy(): int
    {
        self::ensureTable();
        // MySQL DDL commits implicitly; initialize settings before the data transaction.
        AppSetting::get('vault_exclusive_user_id', '0');
        User::ensureRoleColumn();
        $count = 0;
        db()->beginTransaction();
        try {
            foreach (db()->query('SELECT * FROM users ORDER BY id FOR UPDATE')->fetchAll() as $user) {
                if (self::custom((int) $user['id']) !== null) continue;
                self::save((int) $user['id'], self::effective($user), self::delegation($user));
                $count++;
            }
            db()->commit();
        } catch (Throwable $error) {
            if (db()->inTransaction()) db()->rollBack();
            throw $error;
        }
        return $count;
    }
    public static function lastManager(array $target): bool
    {
        if (empty($target['is_active']) || !self::allows($target, 'users.permissions')) return false;
        foreach (User::all() as $user) {
            if ((int) $user['id'] !== (int) $target['id'] && !empty($user['is_active']) && self::allows($user, 'users.permissions')) return false;
        }
        return true;
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
