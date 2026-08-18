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

    public static function index(): void
    {
        ApiResponse::ok([
            'version' => 'v1',
            'status' => 'active',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/v1/health', 'auth' => false],
                ['method' => 'GET', 'path' => '/api/v1/me', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/device-types', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/companies', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/companies/{id}', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/companies/{id}/machines', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/machines/{id}', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/machines/{id}/photos', 'auth' => true],
            ],
        ]);
    }

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
        $user = ApiAuth::user();
        $token = ApiAuth::token();

        ApiResponse::ok([
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'is_admin' => (bool) $user['is_admin'],
            'auth' => [
                'type' => $token ? 'bearer_token' : 'session',
                'token_name' => $token['name'] ?? null,
            ],
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
        $activeOnly = ApiRequest::bool('active_only', false);
        $pagination = ApiRequest::pagination();
        $total = Company::countAll($activeOnly);
        $companies = Company::paginated($activeOnly, (int) $pagination['per_page'], (int) $pagination['offset']);

        ApiResponse::ok(array_map([self::class, 'companyResource'], $companies), [
            'active_only' => $activeOnly,
            'pagination' => ApiRequest::paginationMeta($total, $pagination),
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
            'device_type' => ApiRequest::string('device_type'),
            'tag' => ApiRequest::string('tag'),
            'employee_name' => ApiRequest::string('employee_name'),
            'department' => ApiRequest::string('department'),
            'computer_model' => ApiRequest::string('computer_model'),
            'status' => ApiRequest::string('status', 'active'),
            'created_at' => ApiRequest::string('created_at'),
        ];
        $pagination = ApiRequest::pagination();
        $total = Machine::countByCompany($companyId, $filters);

        $machines = Machine::byCompany($companyId, $filters, (int) $pagination['per_page'], (int) $pagination['offset']);

        ApiResponse::ok(array_map([self::class, 'machineResource'], $machines), [
            'company_id' => $companyId,
            'filters' => ApiRequest::filled($filters),
            'pagination' => ApiRequest::paginationMeta($total, $pagination),
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

}
