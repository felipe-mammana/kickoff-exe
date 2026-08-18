<?php

declare(strict_types=1);

class ApiV1Controller
{
    private const MACHINE_PUBLIC_FIELDS = [
        'id',
        'company_id',
        'company_name',
        'device_type',
        'equipment_name',
        'tag',
        'old_hostname',
        'new_hostname',
        'employee_name',
        'department',
        'brand',
        'computer_model',
        'operating_system',
        'admin_user',
        'install_location',
        'modem_name',
        'ip_address',
        'gateway',
        'carrier',
        'printer_brand',
        'printer_connection_type',
        'printer_shared',
        'notes',
        'tflux_installed',
        'antivirus_installed',
        'requester_in_tflux',
        'is_active',
        'photos_count',
        'created_at',
        'updated_at',
    ];

    public static function health(): void
    {
        ApiResponse::ok([
            'app' => APP_NAME,
            'version' => 'v1',
            'environment' => APP_ENV,
            'time' => date(DATE_ATOM),
        ]);
    }

    public static function me(): void
    {
        $user = current_user();

        ApiResponse::ok([
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'is_admin' => (bool) $user['is_admin'],
        ]);
    }

    public static function deviceTypes(): void
    {
        $types = [];
        foreach (Machine::deviceTypes() as $value => $label) {
            $types[] = ['value' => $value, 'label' => $label];
        }

        ApiResponse::ok($types);
    }

    public static function companies(): void
    {
        $activeOnly = self::queryBool('active_only', false);

        ApiResponse::ok(array_map([self::class, 'companyResource'], Company::all($activeOnly)), [
            'active_only' => $activeOnly,
        ]);
    }

    public static function company(array $params): void
    {
        $company = Company::find((int) $params['id']);

        if (!$company) {
            ApiResponse::error('company_not_found', 'Empresa nao encontrada.', 404);
        }

        ApiResponse::ok(self::companyResource($company));
    }

    public static function companyMachines(array $params): void
    {
        $companyId = (int) $params['id'];
        $company = Company::find($companyId);

        if (!$company) {
            ApiResponse::error('company_not_found', 'Empresa nao encontrada.', 404);
        }

        $filters = [
            'device_type' => self::queryString('device_type'),
            'tag' => self::queryString('tag'),
            'employee_name' => self::queryString('employee_name'),
            'department' => self::queryString('department'),
            'computer_model' => self::queryString('computer_model'),
            'status' => self::queryString('status', 'active'),
            'created_at' => self::queryString('created_at'),
        ];

        $machines = Machine::byCompany($companyId, $filters);

        ApiResponse::ok(array_map([self::class, 'machineResource'], $machines), [
            'company_id' => $companyId,
            'filters' => self::filled($filters),
        ]);
    }

    public static function machine(array $params): void
    {
        $machine = Machine::find((int) $params['id']);

        if (!$machine) {
            ApiResponse::error('machine_not_found', 'Dispositivo nao encontrado.', 404);
        }

        ApiResponse::ok(self::machineResource($machine));
    }

    public static function machinePhotos(array $params): void
    {
        $machine = Machine::find((int) $params['id']);

        if (!$machine) {
            ApiResponse::error('machine_not_found', 'Dispositivo nao encontrado.', 404);
        }

        ApiResponse::ok(array_map([self::class, 'photoResource'], MachinePhoto::byMachine((int) $params['id'])));
    }

    private static function companyResource(array $company): array
    {
        return [
            'id' => (int) $company['id'],
            'name' => (string) $company['name'],
            'tag_pattern' => $company['tag_pattern'] ?? null,
            'is_active' => (bool) ($company['is_active'] ?? true),
            'created_by_name' => $company['created_by_name'] ?? null,
            'updated_by_name' => $company['updated_by_name'] ?? null,
            'created_at' => $company['created_at'] ?? null,
            'updated_at' => $company['updated_at'] ?? null,
        ];
    }

    private static function machineResource(array $machine): array
    {
        $resource = [];
        foreach (self::MACHINE_PUBLIC_FIELDS as $field) {
            if (array_key_exists($field, $machine)) {
                $resource[$field] = $machine[$field];
            }
        }

        foreach (['id', 'company_id', 'photos_count'] as $field) {
            if (isset($resource[$field])) {
                $resource[$field] = (int) $resource[$field];
            }
        }

        foreach (['printer_shared', 'tflux_installed', 'antivirus_installed', 'requester_in_tflux', 'is_active'] as $field) {
            if (isset($resource[$field])) {
                $resource[$field] = (bool) $resource[$field];
            }
        }

        return $resource;
    }

    private static function photoResource(array $photo): array
    {
        return [
            'id' => (int) $photo['id'],
            'machine_id' => (int) $photo['machine_id'],
            'photo_type' => (string) $photo['photo_type'],
            'file_name' => (string) $photo['file_name'],
            'original_name' => (string) $photo['original_name'],
            'mime_type' => (string) $photo['mime_type'],
            'file_size' => (int) $photo['file_size'],
            'url' => UPLOAD_URL . '/' . $photo['file_name'],
            'created_at' => $photo['created_at'] ?? null,
        ];
    }

    private static function queryString(string $key, string $default = ''): string
    {
        return trim((string) ($_GET[$key] ?? $default));
    }

    private static function queryBool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $_GET)) {
            return $default;
        }

        return filter_var($_GET[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private static function filled(array $values): array
    {
        return array_filter($values, static fn ($value): bool => (string) $value !== '');
    }
}
