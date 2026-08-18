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
                ['method' => 'PUT', 'path' => '/api/v1/machines/{id}', 'auth' => true],
                ['method' => 'PATCH', 'path' => '/api/v1/machines/{id}', 'auth' => true],
                ['method' => 'DELETE', 'path' => '/api/v1/machines/{id}', 'auth' => true],
                ['method' => 'GET', 'path' => '/api/v1/machines/{id}/photos', 'auth' => true],
                ['method' => 'POST', 'path' => '/api/v1/machines/{id}/photos', 'auth' => true],
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

    public static function updateMachine(array $params): void
    {
        $machineId = (int) $params['id'];
        $machine = Machine::find($machineId);

        if (!$machine) {
            ApiResponse::error('machine_not_found', 'Dispositivo nao encontrado.', 404);
        }

        $companyId = (int) $machine['company_id'];
        $payload = ApiRequest::json();
        [$data, $errors] = self::validateMachinePayload($payload, $companyId, $machineId, $machine);
        self::abortIfValidationFails($errors);

        $user = ApiAuth::user();
        $data['updated_by'] = (int) $user['id'];
        $changes = self::machineChanges($machine, $data);
        $updateData = $data;
        unset($updateData['company_id']);

        Machine::update($machineId, $updateData);
        $updatedMachine = Machine::find($machineId);

        if ($changes) {
            AuditLog::record([
                'action_type' => 'machine_updated',
                'affected_table' => 'machines',
                'affected_record_id' => $machineId,
                'company_id' => $companyId,
                'machine_id' => $machineId,
                'description' => 'Dispositivo alterado via API.',
                'old_data' => $changes['old'],
                'new_data' => $changes['new'],
            ]);
        }

        ApiResponse::ok(self::machineResource($updatedMachine ?: $data + ['id' => $machineId]), [
            'changed' => (bool) $changes,
        ]);
    }

    public static function deactivateMachine(array $params): void
    {
        $machineId = (int) $params['id'];
        $machine = Machine::find($machineId);

        if (!$machine) {
            ApiResponse::error('machine_not_found', 'Dispositivo nao encontrado.', 404);
        }

        $changed = !empty($machine['is_active']);
        if ($changed) {
            Machine::deactivate($machineId);
            AuditLog::record([
                'action_type' => 'machine_deactivated',
                'affected_table' => 'machines',
                'affected_record_id' => $machineId,
                'company_id' => (int) $machine['company_id'],
                'machine_id' => $machineId,
                'description' => 'Dispositivo desativado via API.',
                'old_data' => ['is_active' => 1],
                'new_data' => ['is_active' => 0],
            ]);
        }

        $updatedMachine = Machine::find($machineId);

        ApiResponse::ok(self::machineResource($updatedMachine ?: $machine), [
            'changed' => $changed,
        ]);
    }

    public static function machinePhotos(array $params): void
    {
        $machine = Machine::find((int) $params['id']);

        if (!$machine) {
            ApiResponse::error('machine_not_found', 'Dispositivo nao encontrado.', 404);
        }

        ApiResponse::ok(array_map([self::class, 'photoResource'], MachinePhoto::byMachine((int) $params['id'])));
    }

    public static function uploadMachinePhotos(array $params): void
    {
        $machineId = (int) $params['id'];
        $machine = Machine::find($machineId);

        if (!$machine) {
            ApiResponse::error('machine_not_found', 'Dispositivo nao encontrado.', 404);
        }

        $defaultPhotoType = self::normalizePhotoType((string) ($_POST['photo_type'] ?? 'general'));
        [$generalPhotos, $generalErrors] = self::storeApiPhotos($machineId, 'photos', $defaultPhotoType);
        [$networkPhotos, $networkErrors] = self::storeApiPhotos($machineId, 'network_photo', 'network_config');
        $stored = array_merge($generalPhotos, $networkPhotos);
        $errors = array_merge($generalErrors, $networkErrors);

        if (!$stored && !$errors) {
            ApiResponse::error('validation_failed', 'Envie ao menos uma foto.', 422, [
                'fields' => ['photos' => ['Envie ao menos uma foto.']],
            ]);
        }

        if (!$stored) {
            ApiResponse::error('validation_failed', 'Nenhuma foto valida foi enviada.', 422, [
                'fields' => ['photos' => $errors],
            ]);
        }

        AuditLog::record([
            'action_type' => 'machine_photos_added',
            'affected_table' => 'machine_photos',
            'affected_record_id' => $machineId,
            'company_id' => (int) $machine['company_id'],
            'machine_id' => $machineId,
            'description' => count($stored) . ' foto(s) adicionada(s) ao dispositivo via API.',
            'new_data' => $stored,
        ]);

        ApiResponse::ok(array_map([self::class, 'photoResource'], $stored), [
            'uploaded' => count($stored),
            'errors' => $errors,
        ], 201);
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

    private static function validateMachinePayload(array $payload, int $companyId, ?int $ignoreId = null, ?array $current = null): array
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

        $deviceType = trim((string) ($payload['device_type'] ?? ($current['device_type'] ?? 'notebook')));
        if (!array_key_exists($deviceType, Machine::deviceTypes())) {
            $errors['device_type'][] = 'Tipo de dispositivo invalido.';
            $deviceType = (string) ($current['device_type'] ?? 'notebook');
        }

        $data = [
            'company_id' => $companyId,
            'device_type' => $deviceType,
            'equipment_name' => self::nullablePayloadString($payload, 'equipment_name', $current['equipment_name'] ?? null),
            'tag' => self::nullablePayloadString($payload, 'tag', $current['tag'] ?? null),
            'old_hostname' => self::nullablePayloadString($payload, 'old_hostname', $current['old_hostname'] ?? null),
            'new_hostname' => self::nullablePayloadString($payload, 'new_hostname', $current['new_hostname'] ?? null),
            'employee_name' => self::nullablePayloadString($payload, 'employee_name', $current['employee_name'] ?? null),
            'department' => self::nullablePayloadString($payload, 'department', $current['department'] ?? null),
            'brand' => self::nullablePayloadString($payload, 'brand', $current['brand'] ?? null),
            'computer_model' => self::nullablePayloadString($payload, 'computer_model', $current['computer_model'] ?? null),
            'operating_system' => self::nullablePayloadString($payload, 'operating_system', $current['operating_system'] ?? null),
            'machine_password' => self::nullablePayloadString($payload, 'machine_password', $current['machine_password'] ?? null),
            'admin_user' => self::nullablePayloadString($payload, 'admin_user', $current['admin_user'] ?? null),
            'admin_password' => self::nullablePayloadString($payload, 'admin_password', $current['admin_password'] ?? null),
            'install_location' => self::nullablePayloadString($payload, 'install_location', $current['install_location'] ?? null),
            'modem_name' => self::nullablePayloadString($payload, 'modem_name', $current['modem_name'] ?? null),
            'ip_address' => self::nullablePayloadString($payload, 'ip_address', $current['ip_address'] ?? null),
            'gateway' => self::nullablePayloadString($payload, 'gateway', $current['gateway'] ?? null),
            'carrier' => self::nullablePayloadString($payload, 'carrier', $current['carrier'] ?? null),
            'printer_brand' => self::nullablePayloadString($payload, 'printer_brand', $current['printer_brand'] ?? null),
            'printer_connection_type' => self::nullablePayloadString($payload, 'printer_connection_type', $current['printer_connection_type'] ?? null),
            'printer_shared' => self::payloadBool($payload, 'printer_shared', (int) ($current['printer_shared'] ?? 0)),
            'notes' => self::nullablePayloadString($payload, 'notes', $current['notes'] ?? null),
            'tflux_installed' => self::payloadBool($payload, 'tflux_installed', (int) ($current['tflux_installed'] ?? 0)),
            'antivirus_installed' => self::payloadBool($payload, 'antivirus_installed', (int) ($current['antivirus_installed'] ?? 0)),
            'requester_in_tflux' => self::payloadBool($payload, 'requester_in_tflux', (int) ($current['requester_in_tflux'] ?? 0)),
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

    private static function machineChanges(array $old, array $new): array
    {
        $labels = [
            'tag' => 'Etiqueta',
            'device_type' => 'Tipo de dispositivo',
            'equipment_name' => 'Nome do equipamento',
            'old_hostname' => 'Hostname antigo',
            'new_hostname' => 'Hostname novo',
            'employee_name' => 'Colaborador',
            'department' => 'Departamento',
            'brand' => 'Marca',
            'computer_model' => 'Modelo do computador',
            'operating_system' => 'Sistema operacional',
            'machine_password' => 'Senha do equipamento',
            'admin_user' => 'Usuario administrador',
            'admin_password' => 'Senha administrador',
            'install_location' => 'Local de instalacao',
            'modem_name' => 'Nome do modem',
            'ip_address' => 'IP de acesso',
            'gateway' => 'Gateway',
            'carrier' => 'Operadora',
            'printer_brand' => 'Marca da impressora',
            'printer_connection_type' => 'Tipo de conexao',
            'printer_shared' => 'Impressora compartilhada',
            'notes' => 'Observacoes',
            'tflux_installed' => 'TFlux instalado',
            'antivirus_installed' => 'Antivirus instalado',
            'requester_in_tflux' => 'Solicitante cadastrado no TFlux',
        ];
        $changes = ['old' => [], 'new' => []];

        foreach ($labels as $field => $label) {
            $oldValue = (string) ($old[$field] ?? '');
            $newValue = (string) ($new[$field] ?? '');

            if ($oldValue !== $newValue) {
                $changes['old'][$label] = in_array($field, ['machine_password', 'admin_password'], true) ? '[protegido]' : $oldValue;
                $changes['new'][$label] = in_array($field, ['machine_password', 'admin_password'], true) ? '[protegido]' : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }

    private static function nullablePayloadString(array $payload, string $field, ?string $default = null): ?string
    {
        if (!array_key_exists($field, $payload)) {
            return $default;
        }

        $value = trim((string) $payload[$field]);

        return $value === '' ? null : $value;
    }

    private static function payloadBool(array $payload, string $field, int $default = 0): int
    {
        return array_key_exists($field, $payload) ? (int) filter_var($payload[$field], FILTER_VALIDATE_BOOLEAN) : $default;
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

    private static function storeApiPhotos(int $machineId, string $inputName, string $photoType): array
    {
        $stored = [];
        $errors = [];

        if (empty($_FILES[$inputName])) {
            return [$stored, $errors];
        }

        $files = self::uploadedFiles($inputName);
        if (!$files) {
            return [$stored, $errors];
        }

        if (!is_dir(UPLOAD_PATH) && !mkdir(UPLOAD_PATH, 0755, true) && !is_dir(UPLOAD_PATH)) {
            ApiResponse::error('upload_path_unavailable', 'Diretorio de upload indisponivel.', 500);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        foreach ($files as $file) {
            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Uma das fotos nao pode ser enviada.';
                continue;
            }

            if ((int) $file['size'] > MAX_UPLOAD_BYTES) {
                $errors[] = 'Uma foto excede o limite de 5MB.';
                continue;
            }

            $tmpName = (string) $file['tmp_name'];
            $mime = $finfo->file($tmpName);

            if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
                $errors[] = 'Formato de imagem invalido. Use JPG, PNG ou WEBP.';
                continue;
            }

            $fileName = $machineId . '-' . bin2hex(random_bytes(12)) . '.' . self::imageExtension((string) $mime);
            $destination = UPLOAD_PATH . '/' . $fileName;

            if (!move_uploaded_file($tmpName, $destination)) {
                $errors[] = 'Nao foi possivel salvar uma das fotos.';
                continue;
            }

            $photoData = [
                'machine_id' => $machineId,
                'photo_type' => $photoType,
                'file_name' => $fileName,
                'original_name' => basename((string) $file['name']),
                'mime_type' => (string) $mime,
                'file_size' => (int) $file['size'],
            ];
            $photoData['id'] = MachinePhoto::create($photoData);
            $photoData['created_at'] = date('Y-m-d H:i:s');
            $stored[] = $photoData;
        }

        return [$stored, $errors];
    }

    private static function uploadedFiles(string $inputName): array
    {
        $files = $_FILES[$inputName] ?? [];
        if (!is_array($files) || !array_key_exists('name', $files)) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [[
                'name' => $files['name'],
                'type' => $files['type'] ?? '',
                'tmp_name' => $files['tmp_name'] ?? '',
                'error' => $files['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'] ?? 0,
            ]];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private static function normalizePhotoType(string $photoType): string
    {
        return $photoType === 'network_config' ? 'network_config' : 'general';
    }

    private static function imageExtension(string $mime): string
    {
        if ($mime === 'image/png') {
            return 'png';
        }

        if ($mime === 'image/webp') {
            return 'webp';
        }

        return 'jpg';
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
