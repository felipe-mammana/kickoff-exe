<?php

declare(strict_types=1);

class AuditLog
{
    private const CRITICAL_ACTIONS = [
        'login_success',
        'login_failed',
        'login_rate_limited',
        'login_2fa_failed',
        'login_inactive_user',
        'company_deleted',
        'company_attachment_deleted',
        'machine_deactivated',
        'machine_photo_removed',
        'vault_credential_deactivated',
        'vault_credential_revealed',
        'vault_credential_reveal_password_failed',
        'user_password_changed',
        'user_password_reset',
        'user_2fa_disabled',
        'audit_retention_updated',
        'audit_logs_retention_cleaned',
        'full_backup_exported',
        'database_imported',
        'orphan_files_cleaned',
    ];

    private const MODULES = [
        'auth' => 'Acesso',
        'companies' => 'Empresas',
        'devices' => 'Dispositivos',
        'vault' => 'Cofre',
        'users' => 'Usuários',
        'settings' => 'Configurações',
        'maintenance' => 'Backup e manutenção',
        'export' => 'Exportações',
    ];

    public static function record(array $data): void
    {
        try {
            $user = current_user();
            $columns = [
                'user_id',
                'user_name',
                'user_email',
                'action_type',
                'affected_table',
                'affected_record_id',
                'company_id',
                'machine_id',
                'description',
                'old_data',
                'new_data',
                'ip_address',
                'session_identifier',
            ];

            $values = [
                'user_id' => $data['user_id'] ?? ($user['id'] ?? null),
                'user_name' => self::limitString($data['user_name'] ?? ($user['name'] ?? null), 120),
                'user_email' => self::limitString($data['user_email'] ?? ($user['email'] ?? null), 160),
                'action_type' => self::limitString($data['action_type'], 80),
                'affected_table' => self::limitString($data['affected_table'] ?? null, 80),
                'affected_record_id' => $data['affected_record_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'machine_id' => $data['machine_id'] ?? null,
                'description' => self::limitString($data['description'], 255) ?? 'Evento registrado.',
                'old_data' => self::encode($data['old_data'] ?? null),
                'new_data' => self::encode($data['new_data'] ?? null),
                'ip_address' => self::limitString(client_ip(), 45),
                'session_identifier' => self::limitString(session_id(), 128),
            ];

            if (self::hasUserAgentColumn()) {
                $columns[] = 'user_agent';
                $values['user_agent'] = self::limitString($_SERVER['HTTP_USER_AGENT'] ?? null, 255);
            }

            $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
            $stmt = db()->prepare(
                'INSERT INTO audit_logs (' . implode(', ', $columns) . ')
                 VALUES (' . implode(', ', $placeholders) . ')'
            );

            $stmt->execute($values);
        } catch (Throwable $exception) {
            error_log('Audit log failed: ' . $exception->getMessage());
        }
    }

    public static function search(array $filters): array
    {
        $sql = 'SELECT a.*, c.name AS company_name, m.tag AS machine_tag
                FROM audit_logs a
                LEFT JOIN companies c ON c.id = a.company_id
                LEFT JOIN machines m ON m.id = a.machine_id
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['company_id'])) {
            $sql .= ' AND a.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if (!empty($filters['machine_id'])) {
            $sql .= ' AND a.machine_id = :machine_id';
            $params['machine_id'] = (int) $filters['machine_id'];
        }

        if (!empty($filters['action_type'])) {
            $sql .= ' AND a.action_type = :action_type';
            $params['action_type'] = $filters['action_type'];
        }

        if (!empty($filters['module'])) {
            $actions = self::actionsForModule((string) $filters['module']);
            if ($actions) {
                $placeholders = [];
                foreach ($actions as $index => $action) {
                    $key = 'module_action_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $action;
                }
                $sql .= ' AND a.action_type IN (' . implode(', ', $placeholders) . ')';
            } else {
                $sql .= ' AND 1 = 0';
            }
        }

        if (($filters['criticality'] ?? '') === 'critical') {
            $placeholders = [];
            foreach (self::CRITICAL_ACTIONS as $index => $action) {
                $key = 'critical_action_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $action;
            }
            $sql .= ' AND a.action_type IN (' . implode(', ', $placeholders) . ')';
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND a.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND a.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT 300';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function actionTypes(): array
    {
        $stmt = db()->query('SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type');

        return array_column($stmt->fetchAll(), 'action_type');
    }

    public static function modules(): array
    {
        return self::MODULES;
    }

    public static function moduleForAction(string $actionType): string
    {
        if (str_starts_with($actionType, 'login_') || $actionType === 'logout') {
            return 'auth';
        }

        if (str_starts_with($actionType, 'company_')) {
            return 'companies';
        }

        if (str_starts_with($actionType, 'machine_')) {
            return 'devices';
        }

        if (str_starts_with($actionType, 'vault_') || $actionType === 'credential_viewed') {
            return 'vault';
        }

        if (str_starts_with($actionType, 'user_')) {
            return 'users';
        }

        if (str_starts_with($actionType, 'api_token_')) {
            return 'settings';
        }

        if (str_contains($actionType, 'backup') || str_contains($actionType, 'database') || str_contains($actionType, 'orphan')) {
            return 'maintenance';
        }

        if (str_starts_with($actionType, 'export_')) {
            return 'export';
        }

        return 'settings';
    }

    public static function moduleLabel(string $module): string
    {
        return self::MODULES[$module] ?? 'Sistema';
    }

    public static function isCritical(string $actionType): bool
    {
        return in_array($actionType, self::CRITICAL_ACTIONS, true);
    }

    public static function criticalCount(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM audit_logs a WHERE 1 = 1';
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= ' AND a.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['company_id'])) {
            $sql .= ' AND a.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if (!empty($filters['action_type'])) {
            $sql .= ' AND a.action_type = :action_type';
            $params['action_type'] = $filters['action_type'];
        }

        if (!empty($filters['module'])) {
            $actions = self::actionsForModule((string) $filters['module']);
            if ($actions) {
                $placeholders = [];
                foreach ($actions as $index => $action) {
                    $key = 'module_count_action_' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $action;
                }
                $sql .= ' AND a.action_type IN (' . implode(', ', $placeholders) . ')';
            } else {
                $sql .= ' AND 1 = 0';
            }
        }

        if (!empty($filters['date_from'])) {
            $sql .= ' AND a.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $sql .= ' AND a.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $placeholders = [];
        foreach (self::CRITICAL_ACTIONS as $index => $action) {
            $key = 'critical_action_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $action;
        }

        $sql .= ' AND a.action_type IN (' . implode(', ', $placeholders) . ')';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function deleteOlderThanDays(int $days): int
    {
        $days = max(1, $days);
        $stmt = db()->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)');
        $stmt->execute();

        return $stmt->rowCount();
    }

    private static function actionsForModule(string $module): array
    {
        if (!isset(self::MODULES[$module])) {
            return [];
        }

        return array_values(array_filter(self::actionTypes(), static fn (string $action): bool => self::moduleForAction($action) === $module));
    }

    public static function byMachine(int $machineId): array
    {
        $stmt = db()->prepare(
            'SELECT *
             FROM audit_logs
             WHERE machine_id = :machine_id
             ORDER BY created_at DESC
             LIMIT 30'
        );
        $stmt->execute(['machine_id' => $machineId]);

        return $stmt->fetchAll();
    }

    public static function byCompany(int $companyId): array
    {
        $stmt = db()->prepare(
            'SELECT *
             FROM audit_logs
             WHERE company_id = :company_id
             ORDER BY created_at DESC
             LIMIT 40'
        );
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll();
    }

    public static function latestAccountAccesses(int $userId, int $limit = 8): array
    {
        $limit = max(1, min(20, $limit));
        $stmt = db()->prepare(
            'SELECT action_type, description, ip_address, user_agent, created_at
             FROM audit_logs
             WHERE user_id = :user_id
               AND action_type IN (:login_success, :logout, :login_2fa_failed, :login_failed)
             ORDER BY created_at DESC
             LIMIT ' . $limit
        );
        $stmt->execute([
            'user_id' => $userId,
            'login_success' => 'login_success',
            'logout' => 'logout',
            'login_2fa_failed' => 'login_2fa_failed',
            'login_failed' => 'login_failed',
        ]);

        return $stmt->fetchAll();
    }

    private static function encode($value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function limitString($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
    }

    private static function hasUserAgentColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn !== null) {
            return $hasColumn;
        }

        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                'table_name' => 'audit_logs',
                'column_name' => 'user_agent',
            ]);
            $hasColumn = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $exception) {
            $hasColumn = false;
        }

        return $hasColumn;
    }
}
