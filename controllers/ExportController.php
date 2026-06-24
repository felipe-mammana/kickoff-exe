<?php

declare(strict_types=1);

class ExportController
{
    private const TYPES = ['companies', 'devices', 'users', 'audit'];
    private const FORMATS = ['csv', 'json'];

    public static function download(): void
    {
        require_auth();

        $type = trim((string) ($_GET['type'] ?? ''));
        $format = strtolower(trim((string) ($_GET['format'] ?? '')));

        if (!in_array($type, self::TYPES, true) || !in_array($format, self::FORMATS, true)) {
            http_response_code(404);
            view('errors/404', ['title' => 'Exportacao nao encontrada']);
            return;
        }

        if (in_array($type, ['companies', 'users', 'audit'], true)) {
            require_admin();
        }

        $payload = self::payload($type);
        self::recordAudit($type, $format, $payload['filters'], count($payload['rows']));

        $filename = self::filename($type, $format);
        if ($format === 'csv') {
            self::sendCsv($filename, $payload['columns'], $payload['rows']);
            return;
        }

        self::sendJson($filename, [
            'exportado_em' => date('Y-m-d H:i:s'),
            'total_registros' => count($payload['rows']),
            'filtros' => $payload['filters'],
            'dados' => $payload['rows'],
        ]);
    }

    private static function payload(string $type): array
    {
        return match ($type) {
            'companies' => self::companiesPayload(),
            'devices' => self::devicesPayload(),
            'users' => self::usersPayload(),
            'audit' => self::auditPayload(),
        };
    }

    private static function companiesPayload(): array
    {
        $filters = [
            'name' => trim((string) ($_GET['name'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];

        $sql = 'SELECT c.id, c.name, c.tag_pattern, c.is_active, creator.name AS created_by_name, c.created_at, c.updated_at
                FROM companies c
                LEFT JOIN users creator ON creator.id = c.created_by
                WHERE 1 = 1';
        $params = [];

        if ($filters['name'] !== '') {
            $sql .= ' AND c.name LIKE :name';
            $params['name'] = '%' . $filters['name'] . '%';
        }

        if ($filters['status'] === 'ativa') {
            $sql .= ' AND c.is_active = 1';
        } elseif ($filters['status'] === 'inativa') {
            $sql .= ' AND c.is_active = 0';
        }

        $sql .= ' ORDER BY c.is_active DESC, c.name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $rows = array_map(static function (array $row): array {
            return [
                'ID' => (int) $row['id'],
                'Nome da empresa' => $row['name'],
                'Padrao etiqueta' => $row['tag_pattern'] ?: '-',
                'Status' => !empty($row['is_active']) ? 'Ativa' : 'Inativa',
                'Cadastrada por' => $row['created_by_name'] ?: '-',
                'Criada em' => $row['created_at'],
                'Ultima alteracao' => $row['updated_at'],
            ];
        }, $stmt->fetchAll());

        return ['filters' => self::filledFilters($filters), 'columns' => self::columns($rows), 'rows' => $rows];
    }

    private static function devicesPayload(): array
    {
        $filters = [
            'company_id' => trim((string) ($_GET['company_id'] ?? '')),
            'device_type' => trim((string) ($_GET['device_type'] ?? '')),
            'tag' => trim((string) ($_GET['tag'] ?? '')),
            'employee_name' => trim((string) ($_GET['employee_name'] ?? '')),
            'department' => trim((string) ($_GET['department'] ?? '')),
            'computer_model' => trim((string) ($_GET['computer_model'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'active')),
            'created_at' => trim((string) ($_GET['created_at'] ?? '')),
        ];

        $sql = 'SELECT m.*, c.name AS company_name
                FROM machines m
                INNER JOIN companies c ON c.id = m.company_id
                WHERE 1 = 1';
        $params = [];

        if ($filters['company_id'] !== '') {
            $sql .= ' AND m.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if ($filters['status'] === 'inactive') {
            $sql .= ' AND m.is_active = 0';
        } elseif ($filters['status'] !== 'all') {
            $sql .= ' AND m.is_active = 1';
        }

        if ($filters['device_type'] !== '') {
            $sql .= ' AND m.device_type = :device_type';
            $params['device_type'] = $filters['device_type'];
        }

        foreach (['tag', 'employee_name', 'department', 'computer_model'] as $field) {
            if ($filters[$field] !== '') {
                $sql .= " AND m.{$field} LIKE :{$field}";
                $params[$field] = '%' . $filters[$field] . '%';
            }
        }

        if ($filters['created_at'] !== '') {
            $sql .= ' AND DATE(m.created_at) = :created_at';
            $params['created_at'] = $filters['created_at'];
        }

        $sql .= ' ORDER BY m.updated_at DESC';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $deviceTypes = Machine::deviceTypes();
        $rows = array_map(static function (array $row) use ($deviceTypes): array {
            return [
                'ID' => (int) $row['id'],
                'Empresa' => $row['company_name'],
                'Tipo' => $deviceTypes[$row['device_type'] ?? ''] ?? 'Dispositivo',
                'Etiqueta' => $row['tag'] ?: '-',
                'Hostname antigo' => $row['old_hostname'] ?: '-',
                'Hostname novo' => $row['new_hostname'] ?: '-',
                'Responsavel' => $row['employee_name'] ?: '-',
                'Departamento' => $row['department'] ?: '-',
                'Marca' => ($row['brand'] ?: $row['printer_brand']) ?: '-',
                'Modelo' => $row['computer_model'] ?: '-',
                'Sistema operacional' => $row['operating_system'] ?: '-',
                'Local de instalacao' => $row['install_location'] ?: '-',
                'IP' => $row['ip_address'] ?: '-',
                'Gateway' => $row['gateway'] ?: '-',
                'Operadora' => $row['carrier'] ?: '-',
                'TFlux instalado' => !empty($row['tflux_installed']) ? 'Sim' : 'Nao',
                'Antivirus instalado' => !empty($row['antivirus_installed']) ? 'Sim' : 'Nao',
                'Solicitante no TFlux' => !empty($row['requester_in_tflux']) ? 'Sim' : 'Nao',
                'Status' => !empty($row['is_active']) ? 'Ativo' : 'Inativo',
                'Criado em' => $row['created_at'],
                'Atualizado em' => $row['updated_at'],
            ];
        }, $stmt->fetchAll());

        return ['filters' => self::filledFilters($filters), 'columns' => self::columns($rows), 'rows' => $rows];
    }

    private static function usersPayload(): array
    {
        $filters = [
            'query' => trim((string) ($_GET['query'] ?? '')),
            'profile' => trim((string) ($_GET['profile'] ?? '')),
        ];

        $sql = 'SELECT id, name, email, is_admin, created_at FROM users WHERE 1 = 1';
        $params = [];

        if ($filters['query'] !== '') {
            $sql .= ' AND (name LIKE :query OR email LIKE :query)';
            $params['query'] = '%' . $filters['query'] . '%';
        }

        if ($filters['profile'] === 'admin') {
            $sql .= ' AND is_admin = 1';
        } elseif ($filters['profile'] === 'standard') {
            $sql .= ' AND is_admin = 0';
        }

        $sql .= ' ORDER BY name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        $rows = array_map(static function (array $row): array {
            return [
                'ID' => (int) $row['id'],
                'Usuario' => $row['name'],
                'E-mail' => $row['email'],
                'Perfil' => !empty($row['is_admin']) ? 'Administrador' : 'Usuario padrao',
                'Status' => 'Ativo',
                'Criado em' => $row['created_at'] ?? '-',
            ];
        }, $stmt->fetchAll());

        return ['filters' => self::filledFilters($filters), 'columns' => self::columns($rows), 'rows' => $rows];
    }

    private static function auditPayload(): array
    {
        $filters = [
            'user_id' => trim((string) ($_GET['user_id'] ?? '')),
            'company_id' => trim((string) ($_GET['company_id'] ?? '')),
            'action_type' => trim((string) ($_GET['action_type'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        $rows = array_map(static function (array $row): array {
            return [
                'ID' => (int) $row['id'],
                'Evento' => $row['action_type'],
                'Descricao' => $row['description'],
                'Usuario' => $row['user_name'] ?: '-',
                'E-mail' => $row['user_email'] ?: '-',
                'IP' => $row['ip_address'] ?: '-',
                'Empresa' => $row['company_name'] ?: '-',
                'Tabela afetada' => $row['affected_table'] ?: '-',
                'Registro afetado' => $row['affected_record_id'] ?: '-',
                'Antes' => $row['old_data'] ?: '-',
                'Depois' => $row['new_data'] ?: '-',
                'Criado em' => $row['created_at'],
            ];
        }, AuditLog::search($filters));

        return ['filters' => self::filledFilters($filters), 'columns' => self::columns($rows), 'rows' => $rows];
    }

    private static function sendCsv(string $filename, array $columns, array $rows): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo "\xEF\xBB\xBF";
        $output = fopen('php://output', 'wb');
        fputcsv($output, $columns, ';', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($output, array_map(static fn ($column) => (string) ($row[$column] ?? ''), $columns), ';', '"', '\\');
        }

        fclose($output);
        exit;
    }

    private static function sendJson(string $filename, array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private static function recordAudit(string $type, string $format, array $filters, int $total): void
    {
        AuditLog::record([
            'action_type' => 'export_performed',
            'affected_table' => self::tableName($type),
            'description' => sprintf('Exportacao de %s em %s realizada.', self::label($type), strtoupper($format)),
            'new_data' => [
                'Formato' => strtoupper($format),
                'Tela' => self::label($type),
                'Total de registros' => $total,
                'Filtros' => $filters,
            ],
        ]);
    }

    private static function filename(string $type, string $format): string
    {
        return self::tableName($type) . '_' . date('Y-m-d') . '.' . $format;
    }

    private static function tableName(string $type): string
    {
        return match ($type) {
            'companies' => 'empresas',
            'devices' => 'dispositivos',
            'users' => 'usuarios',
            'audit' => 'logs_auditoria',
        };
    }

    private static function label(string $type): string
    {
        return match ($type) {
            'companies' => 'empresas',
            'devices' => 'dispositivos',
            'users' => 'usuarios',
            'audit' => 'logs de auditoria',
        };
    }

    private static function columns(array $rows): array
    {
        return $rows ? array_keys($rows[0]) : ['Sem registros'];
    }

    private static function filledFilters(array $filters): array
    {
        return array_filter($filters, static fn ($value): bool => (string) $value !== '');
    }
}
