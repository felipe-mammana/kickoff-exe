<?php

declare(strict_types=1);

class AuditLog
{
    public static function record(array $data): void
    {
        try {
            $user = current_user();
            $stmt = db()->prepare(
                'INSERT INTO audit_logs (
                    user_id, user_name, user_email, action_type, affected_table,
                    affected_record_id, company_id, machine_id, description,
                    old_data, new_data, ip_address, session_identifier
                ) VALUES (
                    :user_id, :user_name, :user_email, :action_type, :affected_table,
                    :affected_record_id, :company_id, :machine_id, :description,
                    :old_data, :new_data, :ip_address, :session_identifier
                )'
            );

            $stmt->execute([
                'user_id' => $data['user_id'] ?? ($user['id'] ?? null),
                'user_name' => $data['user_name'] ?? ($user['name'] ?? null),
                'user_email' => $data['user_email'] ?? ($user['email'] ?? null),
                'action_type' => $data['action_type'],
                'affected_table' => $data['affected_table'] ?? null,
                'affected_record_id' => $data['affected_record_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'machine_id' => $data['machine_id'] ?? null,
                'description' => $data['description'],
                'old_data' => self::encode($data['old_data'] ?? null),
                'new_data' => self::encode($data['new_data'] ?? null),
                'ip_address' => client_ip(),
                'session_identifier' => session_id(),
            ]);
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

    private static function encode($value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
