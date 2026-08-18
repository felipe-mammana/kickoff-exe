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
                ['method' => 'POST', 'path' => '/api/v1/companies', 'auth' => true, 'admin' => true],
                ['method' => 'GET', 'path' => '/api/v1/companies/{id}', 'auth' => true],
                ['method' => 'PUT', 'path' => '/api/v1/companies/{id}', 'auth' => true, 'admin' => true],
                ['method' => 'PATCH', 'path' => '/api/v1/companies/{id}', 'auth' => true, 'admin' => true],
                ['method' => 'DELETE', 'path' => '/api/v1/companies/{id}', 'auth' => true, 'admin' => true],
                ['method' => 'GET', 'path' => '/api/v1/companies/{id}/machines', 'auth' => true],
                ['method' => 'POST', 'path' => '/api/v1/companies/{id}/machines', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/machines/{id}', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/machines/{id}/photos', 'auth' => true],
            ],
        ]);
    }

    public static function createCompany(): void
    {
        $payload = ApiRequest::json();
        [$data, $errors] = self::validateCompanyPayload($payload, null, true);
        self::abortIfValidationFails($errors);

        $user = ApiAuth::user();
        $data['created_by'] = (int) $user['id'];
        $data['updated_by'] = (int) $user['id'];
        $companyId = Company::create($data);
        $company = Company::find($companyId);

        AuditLog::record([
            'action_type' => 'company_created',
            'affected_table' => 'companies',
            'affected_record_id' => $companyId,
            'company_id' => $companyId,
            'description' => 'Empresa cadastrada via API.',
            'new_data' => $data,
        ]);

        header('Location: /api/v1/companies/' . $companyId);
        ApiResponse::ok(self::companyResource($company ?: ['id' => $companyId] + $data), [], 201);
    }

    public static function updateCompany(array $params): void
    {
        $companyId = (int) $params['id'];
        $company = Company::find($companyId);

        if (!$company) {
            ApiResponse::error('company_not_found', 'Empresa nao encontrada.', 404);
        }

        $payload = ApiRequest::json();
        [$data, $errors] = self::validateCompanyPayload($payload, $companyId, false, $company);
        self::abortIfValidationFails($errors);

        $user = ApiAuth::user();
        $data['updated_by'] = (int) $user['id'];
        $changes = self::companyChanges($company, $data);
        Company::update($companyId, $data);
        $updatedCompany = Company::find($companyId);

        if ($changes) {
            AuditLog::record([
                'action_type' => 'company_updated',
                'affected_table' => 'companies',
                'affected_record_id' => $companyId,
                'company_id' => $companyId,
                'description' => 'Empresa alterada via API.',
                'old_data' => $changes['old'],
                'new_data' => $changes['new'],
            ]);
        }

        ApiResponse::ok(self::companyResource($updatedCompany ?: $data + ['id' => $companyId]), [
            'changed' => (bool) $changes,
        ]);
    }

    public static function deactivateCompany(array $params): void
    {
        $companyId = (int) $params['id'];
        $company = Company::find($companyId);

        if (!$company) {
            ApiResponse::error('company_not_found', 'Empresa nao encontrada.', 404);
        }

        $changed = !empty($company['is_active']);
        if ($changed) {
            $user = ApiAuth::user();
            Company::deactivate($companyId, (int) $user['id']);
            AuditLog::record([
                'action_type' => 'company_deactivated',
                'affected_table' => 'companies',
                'affected_record_id' => $companyId,
                'company_id' => $companyId,
                'description' => 'Empresa desativada via API.',
                'old_data' => ['Status' => 'Ativa'],
                'new_data' => ['Status' => 'Inativa'],
            ]);
        }

        $updatedCompany = Company::find($companyId);

        ApiResponse::ok(self::companyResource($updatedCompany ?: $company), [
            'changed' => $changed,
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

    public static function createMachine(array $params): void
    {
        $companyId = (int) $params['id'];
        $company = Company::find($companyId);

        if (!$company) {
            ApiResponse::error('company_not_found', 'Empresa nao encontrada.', 404);
        }

        $payload = ApiRequest::json();
        [$data, $errors] = self::validateMachinePayload($payload, $companyId);
        self::abortIfValidationFails($errors);

        $user = ApiAuth::user();
        $data['created_by'] = (int) $user['id'];
        $data['updated_by'] = (int) $user['id'];
        $machineId = Machine::create($data);
        $machine = Machine::find($machineId);

        AuditLog::record([
            'action_type' => 'machine_created',
            'affected_table' => 'machines',
            'affected_record_id' => $machineId,
            'company_id' => $companyId,
            'machine_id' => $machineId,
            'description' => 'Novo dispositivo cadastrado via API.',
            'new_data' => self::sanitizeMachineAuditData($data),
        ]);

        header('Location: /api/v1/machines/' . $machineId);
        ApiResponse::ok(self::machineResource($machine ?: ['id' => $machineId] + $data), [], 201);
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

    private static function validateCompanyPayload(array $payload, ?int $ignoreId = null, bool $creating = false, ?array $current = null): array
    {
        $rules = [
            'name' => $creating ? ['required', 'string', 'max' => 160] : ['string', 'max' => 160],
            'tag_pattern' => ['string', 'max' => 160],
            'is_active' => ['bool'],
        ];
        $errors = ApiValidator::validate($payload, $rules);

        $name = trim((string) ($payload['name'] ?? ($current['name'] ?? '')));
        if (!$errors && $name !== '' && Company::duplicateNameExists($name, $ignoreId)) {
            $errors['name'][] = 'Ja existe uma empresa com este nome.';
        }

        $data = [
            'name' => $name,
            'tag_pattern' => trim((string) ($payload['tag_pattern'] ?? ($current['tag_pattern'] ?? ''))),
            'is_active' => array_key_exists('is_active', $payload)
                ? (int) filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN)
                : (int) ($current['is_active'] ?? 1),
        ];

        return [$data, $errors];
    }

    private static function validateMachinePayload(array $payload, int $companyId, ?int $ignoreId = null): array
    {
        $stringRules = ['string', 'max' => 160];
        $rules = [
            'device_type' => ['string', 'max' => 40],
            'equipment_name' => $stringRules,
            'tag' => ['string', 'max' => 80],
            'old_hostname' => ['string', 'max' => 120],
            'new_hostname' => ['string', 'max' => 120],
            'employee_name' => $stringRules,
            'department' => ['string', 'max' => 120],
            'brand' => $stringRules,
            'computer_model' => $stringRules,
            'operating_system' => $stringRules,
            'machine_password' => $stringRules,
            'admin_user' => $stringRules,
            'admin_password' => $stringRules,
            'install_location' => $stringRules,
            'modem_name' => $stringRules,
            'ip_address' => ['string', 'max' => 80],
            'gateway' => ['string', 'max' => 80],
            'carrier' => $stringRules,
            'printer_brand' => $stringRules,
            'printer_connection_type' => ['string', 'max' => 40],
            'printer_shared' => ['bool'],
            'notes' => ['string', 'max' => 5000],
            'tflux_installed' => ['bool'],
            'antivirus_installed' => ['bool'],
            'requester_in_tflux' => ['bool'],
        ];
        $errors = ApiValidator::validate($payload, $rules);

        $deviceType = trim((string) ($payload['device_type'] ?? 'notebook'));
        if (!array_key_exists($deviceType, Machine::deviceTypes())) {
            $errors['device_type'][] = 'Tipo de dispositivo invalido.';
            $deviceType = 'notebook';
        }

        $data = [
            'company_id' => $companyId,
            'device_type' => $deviceType,
            'equipment_name' => self::nullablePayloadString($payload, 'equipment_name'),
            'tag' => self::nullablePayloadString($payload, 'tag'),
            'old_hostname' => self::nullablePayloadString($payload, 'old_hostname'),
            'new_hostname' => self::nullablePayloadString($payload, 'new_hostname'),
            'employee_name' => self::nullablePayloadString($payload, 'employee_name'),
            'department' => self::nullablePayloadString($payload, 'department'),
            'brand' => self::nullablePayloadString($payload, 'brand'),
            'computer_model' => self::nullablePayloadString($payload, 'computer_model'),
            'operating_system' => self::nullablePayloadString($payload, 'operating_system'),
            'machine_password' => self::nullablePayloadString($payload, 'machine_password'),
            'admin_user' => self::nullablePayloadString($payload, 'admin_user'),
            'admin_password' => self::nullablePayloadString($payload, 'admin_password'),
            'install_location' => self::nullablePayloadString($payload, 'install_location'),
            'modem_name' => self::nullablePayloadString($payload, 'modem_name'),
            'ip_address' => self::nullablePayloadString($payload, 'ip_address'),
            'gateway' => self::nullablePayloadString($payload, 'gateway'),
            'carrier' => self::nullablePayloadString($payload, 'carrier'),
            'printer_brand' => self::nullablePayloadString($payload, 'printer_brand'),
            'printer_connection_type' => self::nullablePayloadString($payload, 'printer_connection_type'),
            'printer_shared' => self::payloadBool($payload, 'printer_shared'),
            'notes' => self::nullablePayloadString($payload, 'notes'),
            'tflux_installed' => self::payloadBool($payload, 'tflux_installed'),
            'antivirus_installed' => self::payloadBool($payload, 'antivirus_installed'),
            'requester_in_tflux' => self::payloadBool($payload, 'requester_in_tflux'),
        ];

        foreach (self::requiredMachineFieldsForType($deviceType) as $field) {
            if (($data[$field] ?? null) === null || $data[$field] === '') {
                $errors[$field][] = 'Campo obrigatorio para este tipo de dispositivo.';
            }
        }

        if (($data['tag'] ?? '') !== '' && Machine::duplicateExists($companyId, 'tag', (string) $data['tag'], $ignoreId)) {
            $errors['tag'][] = 'Esta etiqueta ja existe nesta empresa.';
        }

        if (($data['new_hostname'] ?? '') !== '' && Machine::duplicateExists($companyId, 'new_hostname', (string) $data['new_hostname'], $ignoreId)) {
            $errors['new_hostname'][] = 'Este hostname novo ja existe nesta empresa.';
        }

        return [$data, $errors];
    }

    private static function abortIfValidationFails(array $errors): void
    {
        if ($errors) {
            ApiResponse::error('validation_failed', 'Revise os campos enviados.', 422, ['fields' => $errors]);
        }
    }

    private static function companyChanges(array $old, array $new): array
    {
        $labels = [
            'name' => 'Nome da empresa',
            'tag_pattern' => 'Padrao de etiqueta',
            'is_active' => 'Status',
        ];
        $changes = ['old' => [], 'new' => []];

        foreach ($labels as $field => $label) {
            $oldValue = (string) ($old[$field] ?? '');
            $newValue = (string) ($new[$field] ?? '');

            if ($oldValue !== $newValue) {
                $changes['old'][$label] = $field === 'is_active' ? ($oldValue === '1' ? 'Ativa' : 'Inativa') : $oldValue;
                $changes['new'][$label] = $field === 'is_active' ? ($newValue === '1' ? 'Ativa' : 'Inativa') : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }

    private static function nullablePayloadString(array $payload, string $field): ?string
    {
        $value = trim((string) ($payload[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    private static function payloadBool(array $payload, string $field): int
    {
        return array_key_exists($field, $payload) ? (int) filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN) : 0;
    }

    private static function requiredMachineFieldsForType(string $type): array
    {
        $map = [
            'notebook' => ['tag', 'old_hostname', 'new_hostname', 'employee_name', 'department', 'computer_model', 'machine_password'],
            'cpu' => ['tag', 'old_hostname', 'new_hostname', 'employee_name', 'department', 'computer_model', 'machine_password'],
            'roteador' => ['tag', 'computer_model', 'admin_user', 'admin_password', 'ip_address'],
            'access_point' => ['install_location', 'tag', 'computer_model'],
            'modem' => ['tag', 'computer_model', 'admin_user', 'admin_password', 'carrier'],
            'impressora' => ['tag', 'brand', 'computer_model', 'printer_connection_type'],
            'outros' => ['tag', 'computer_model'],
        ];

        return $map[$type] ?? $map['notebook'];
    }

    private static function sanitizeMachineAuditData(array $data): array
    {
        if (array_key_exists('machine_password', $data)) {
            $data['machine_password'] = '[protegido]';
        }
        if (array_key_exists('admin_password', $data)) {
            $data['admin_password'] = '[protegido]';
        }

        return $data;
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
