<?php

declare(strict_types=1);

class Machine
{
    public const DEVICE_TYPES = [
        'notebook' => 'Notebook',
        'cpu' => 'CPU / Desktop',
        'roteador' => 'Roteador',
        'access_point' => 'Access Point',
        'modem' => 'Modem',
        'impressora' => 'Impressora',
        'outros' => 'Outros dispositivos',
    ];

    private const ENCRYPTED_FIELDS = ['machine_password', 'admin_password'];

    public static function deviceTypes(): array
    {
        return self::DEVICE_TYPES;
    }

    public static function typeLabel(?string $type): string
    {
        return self::DEVICE_TYPES[$type ?? ''] ?? 'Dispositivo';
    }

    public static function byCompany(int $companyId, array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        $sql =
            'SELECT m.*, COUNT(p.id) AS photos_count
             FROM machines m
             LEFT JOIN machine_photos p ON p.machine_id = m.id
             WHERE m.company_id = :company_id';
        $params = self::companyFilterParams($companyId, $filters);
        $sql .= self::companyFilterSql($filters);

        $sql .= ' GROUP BY m.id ORDER BY m.updated_at DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = db()->prepare($sql);
        if ($limit !== null) {
            $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        }
        $stmt->execute($params);

        return array_map([self::class, 'decryptCredentials'], $stmt->fetchAll());
    }

    public static function countByCompany(int $companyId, array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM machines m WHERE m.company_id = :company_id';
        $params = self::companyFilterParams($companyId, $filters);
        $sql .= self::companyFilterSql($filters);

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public static function stats(int $companyId): array
    {
        $stmt = db()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(tflux_installed = 1) AS tflux,
                SUM(antivirus_installed = 1) AS antivirus,
                SUM(requester_in_tflux = 1) AS requesters,
                SUM(device_type = \'notebook\') AS notebooks,
                SUM(device_type = \'cpu\') AS cpus,
                SUM(device_type = \'impressora\') AS printers
             FROM machines
             WHERE company_id = :company_id AND is_active = 1'
        );
        $stmt->execute(['company_id' => $companyId]);
        $stats = $stmt->fetch() ?: [];

        return [
            'total' => (int) ($stats['total'] ?? 0),
            'tflux' => (int) ($stats['tflux'] ?? 0),
            'antivirus' => (int) ($stats['antivirus'] ?? 0),
            'requesters' => (int) ($stats['requesters'] ?? 0),
            'notebooks' => (int) ($stats['notebooks'] ?? 0),
            'cpus' => (int) ($stats['cpus'] ?? 0),
            'printers' => (int) ($stats['printers'] ?? 0),
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT m.*, c.name AS company_name
             FROM machines m
             INNER JOIN companies c ON c.id = m.company_id
             WHERE m.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $machine = $stmt->fetch();

        return $machine ? self::decryptCredentials($machine) : null;
    }

    public static function create(array $data): int
    {
        $data = self::encryptCredentials($data);
        $stmt = db()->prepare(
            'INSERT INTO machines (
                company_id, device_type, equipment_name, tag, old_hostname, new_hostname, employee_name, department,
                brand, computer_model, operating_system, machine_password, admin_user, admin_password, install_location,
                modem_name, ip_address, gateway, carrier, printer_brand, printer_connection_type, printer_shared, notes,
                tflux_installed, antivirus_installed, requester_in_tflux, created_by, updated_by
             ) VALUES (
                :company_id, :device_type, :equipment_name, :tag, :old_hostname, :new_hostname, :employee_name, :department,
                :brand, :computer_model, :operating_system, :machine_password, :admin_user, :admin_password, :install_location,
                :modem_name, :ip_address, :gateway, :carrier, :printer_brand, :printer_connection_type, :printer_shared, :notes,
                :tflux_installed, :antivirus_installed, :requester_in_tflux, :created_by, :updated_by
             )'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $data = self::encryptCredentials($data);
        $data['id'] = $id;
        $stmt = db()->prepare(
            'UPDATE machines SET
                tag = :tag,
                device_type = :device_type,
                equipment_name = :equipment_name,
                old_hostname = :old_hostname,
                new_hostname = :new_hostname,
                employee_name = :employee_name,
                department = :department,
                brand = :brand,
                computer_model = :computer_model,
                operating_system = :operating_system,
                machine_password = :machine_password,
                admin_user = :admin_user,
                admin_password = :admin_password,
                install_location = :install_location,
                modem_name = :modem_name,
                ip_address = :ip_address,
                gateway = :gateway,
                carrier = :carrier,
                printer_brand = :printer_brand,
                printer_connection_type = :printer_connection_type,
                printer_shared = :printer_shared,
                notes = :notes,
                tflux_installed = :tflux_installed,
                antivirus_installed = :antivirus_installed,
                requester_in_tflux = :requester_in_tflux,
                updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public static function deactivate(int $id): void
    {
        $stmt = db()->prepare('UPDATE machines SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function duplicateExists(int $companyId, string $field, string $value, ?int $ignoreId = null): bool
    {
        if (!in_array($field, ['tag', 'new_hostname'], true) || $value === '') {
            return false;
        }

        $sql = "SELECT id FROM machines WHERE company_id = :company_id AND {$field} = :value";
        $params = ['company_id' => $companyId, 'value' => $value];

        if ($ignoreId) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    private static function companyFilterSql(array $filters): string
    {
        $sql = '';
        $status = $filters['status'] ?? 'active';

        if ($status === 'inactive') {
            $sql .= ' AND m.is_active = 0';
        } elseif ($status !== 'all') {
            $sql .= ' AND m.is_active = 1';
        }

        if (!empty($filters['device_type'])) {
            $sql .= ' AND m.device_type = :device_type';
        }

        foreach (['tag', 'employee_name', 'department', 'computer_model'] as $field) {
            if (!empty($filters[$field])) {
                $sql .= " AND m.{$field} LIKE :{$field}";
            }
        }

        if (!empty($filters['created_at'])) {
            $sql .= ' AND DATE(m.created_at) = :created_at';
        }

        return $sql;
    }

    private static function companyFilterParams(int $companyId, array $filters): array
    {
        $params = ['company_id' => $companyId];

        if (!empty($filters['device_type'])) {
            $params['device_type'] = $filters['device_type'];
        }

        foreach (['tag', 'employee_name', 'department', 'computer_model'] as $field) {
            if (!empty($filters[$field])) {
                $params[$field] = '%' . $filters[$field] . '%';
            }
        }

        if (!empty($filters['created_at'])) {
            $params['created_at'] = $filters['created_at'];
        }

        return $params;
    }

    private static function encryptCredentials(array $data): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = CredentialCrypto::encrypt($data[$field]);
            }
        }

        return $data;
    }

    private static function decryptCredentials(array $data): array
    {
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = CredentialCrypto::decrypt($data[$field]);
            }
        }

        return $data;
    }
}
