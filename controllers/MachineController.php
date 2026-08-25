<?php

declare(strict_types=1);

class MachineController
{
    public static function create(): void
    {
        require_auth();
        $companyId = (int) ($_GET['company_id'] ?? 0);
        $company = Company::find($companyId);

        if (!$company) {
            flash('danger', 'Selecione uma empresa valida.');
            redirect('/');
        }

        view('machines/form', [
            'title' => 'Novo dispositivo',
            'company' => $company,
            'machine' => null,
            'photos' => [],
            'errors' => [],
            'action' => '/?route=machines.store',
            'deviceTypes' => Machine::deviceTypes(),
        ]);
    }

    public static function store(): void
    {
        require_auth();
        verify_csrf();

        [$data, $errors] = self::validatedData();

        if ($errors) {
            view('machines/form', [
                'title' => 'Novo dispositivo',
                'company' => Company::find((int) ($data['company_id'] ?? 0)),
                'machine' => $data,
                'photos' => [],
                'errors' => $errors,
                'action' => '/?route=machines.store',
                'deviceTypes' => Machine::deviceTypes(),
            ]);
            return;
        }

        $data['created_by'] = current_user()['id'];
        $data['updated_by'] = current_user()['id'];
        $machineId = Machine::create($data);
        AuditLog::record([
            'action_type' => 'machine_created',
            'affected_table' => 'machines',
            'affected_record_id' => $machineId,
            'company_id' => (int) $data['company_id'],
            'machine_id' => $machineId,
            'description' => 'Novo dispositivo cadastrado.',
            'new_data' => self::sanitizeAuditData($data),
        ]);

        $photos = array_merge(
            self::storePhotos($machineId),
            self::storePhotos($machineId, 'network_photo', 'network_config')
        );
        if ($photos) {
            AuditLog::record([
                'action_type' => 'machine_photos_added',
                'affected_table' => 'machine_photos',
                'affected_record_id' => $machineId,
                'company_id' => (int) $data['company_id'],
                'machine_id' => $machineId,
                'description' => count($photos) . ' foto(s) adicionada(s) ao dispositivo.',
                'new_data' => $photos,
            ]);
        }

        flash('success', 'Dispositivo cadastrado com sucesso.');
        redirect('/?route=machines.show&id=' . $machineId);
    }

    public static function show(): void
    {
        require_auth();
        $machine = self::requireMachine();
        $photos = MachinePhoto::byMachine((int) $machine['id']);

        view('machines/show', [
            'title' => self::machineTitle($machine),
            'machine' => $machine,
            'photos' => $photos,
            'history' => AuditLog::byMachine((int) $machine['id']),
        ]);
    }

    public static function edit(): void
    {
        require_auth();
        $machine = self::requireMachine();
        $photos = MachinePhoto::byMachine((int) $machine['id']);

        view('machines/form', [
            'title' => 'Editar dispositivo',
            'company' => Company::find((int) $machine['company_id']),
            'machine' => $machine,
            'photos' => $photos,
            'errors' => [],
            'action' => '/?route=machines.update&id=' . (int) $machine['id'],
            'deviceTypes' => Machine::deviceTypes(),
        ]);
    }

    public static function update(): void
    {
        require_auth();
        verify_csrf();
        $machine = self::requireMachine();

        [$data, $errors] = self::validatedData((int) $machine['id']);

        if ($errors) {
            view('machines/form', [
                'title' => 'Editar dispositivo',
                'company' => Company::find((int) $machine['company_id']),
                'machine' => array_merge($machine, $data),
                'photos' => MachinePhoto::byMachine((int) $machine['id']),
                'errors' => $errors,
                'action' => '/?route=machines.update&id=' . (int) $machine['id'],
                'deviceTypes' => Machine::deviceTypes(),
            ]);
            return;
        }

        $changes = self::changedFields($machine, $data);
        unset($data['company_id']);
        $data['updated_by'] = current_user()['id'];
        Machine::update((int) $machine['id'], $data);
        if ($changes) {
            AuditLog::record([
                'action_type' => 'machine_updated',
                'affected_table' => 'machines',
                'affected_record_id' => (int) $machine['id'],
                'company_id' => (int) $machine['company_id'],
                'machine_id' => (int) $machine['id'],
                'description' => 'Dados do dispositivo alterados.',
                'old_data' => $changes['old'],
                'new_data' => $changes['new'],
            ]);
        }

        $photos = array_merge(self::storePhotos((int) $machine['id']), self::storePhotos((int) $machine['id'], 'network_photo', 'network_config'));
        if ($photos) {
            AuditLog::record([
                'action_type' => 'machine_photos_added',
                'affected_table' => 'machine_photos',
                'affected_record_id' => (int) $machine['id'],
                'company_id' => (int) $machine['company_id'],
                'machine_id' => (int) $machine['id'],
                'description' => count($photos) . ' foto(s) adicionada(s) ao dispositivo.',
                'new_data' => $photos,
            ]);
        }

        flash('success', 'Dispositivo atualizado com sucesso.');
        redirect('/?route=machines.show&id=' . (int) $machine['id']);
    }

    public static function deactivate(): void
    {
        require_auth();
        verify_csrf();
        $machine = self::requireMachine();

        Machine::deactivate((int) $machine['id']);
        AuditLog::record([
            'action_type' => 'machine_deactivated',
            'affected_table' => 'machines',
            'affected_record_id' => (int) $machine['id'],
            'company_id' => (int) $machine['company_id'],
            'machine_id' => (int) $machine['id'],
            'description' => 'Dispositivo desativado.',
            'old_data' => ['is_active' => 1],
            'new_data' => ['is_active' => 0],
        ]);

        flash('success', 'Dispositivo desativado. O registro saiu da listagem principal.');
        redirect('/?company_id=' . (int) $machine['company_id']);
    }

    public static function deletePhoto(): void
    {
        require_auth();
        verify_csrf();
        $photo = MachinePhoto::find((int) ($_POST['photo_id'] ?? 0));

        if (!$photo) {
            flash('danger', 'Foto nao encontrada.');
            redirect('/');
        }

        $path = UPLOAD_PATH . '/' . $photo['file_name'];
        if (is_file($path)) {
            unlink($path);
        }

        $machine = Machine::find((int) $photo['machine_id']);
        MachinePhoto::delete((int) $photo['id']);
        AuditLog::record([
            'action_type' => 'machine_photo_removed',
            'affected_table' => 'machine_photos',
            'affected_record_id' => (int) $photo['id'],
            'company_id' => $machine['company_id'] ?? null,
            'machine_id' => (int) $photo['machine_id'],
            'description' => 'Foto removida do dispositivo.',
            'old_data' => [
                'file_name' => $photo['file_name'],
                'original_name' => $photo['original_name'],
                'photo_topic' => $photo['photo_topic'] ?? 'equipamento',
                'location_name' => $photo['location_name'] ?? null,
                'mime_type' => $photo['mime_type'],
                'file_size' => (int) $photo['file_size'],
            ],
        ]);
        flash('success', 'Foto removida.');
        redirect('/?route=machines.edit&id=' . (int) $photo['machine_id']);
    }

    private static function requireMachine(): array
    {
        $machine = Machine::find((int) ($_GET['id'] ?? 0));

        if (!$machine) {
            http_response_code(404);
            view('errors/404', ['title' => 'Dispositivo nao encontrado']);
            exit;
        }

        return $machine;
    }

    private static function machineTitle(array $machine): string
    {
        foreach (['tag', 'new_hostname', 'old_hostname', 'modem_name', 'install_location', 'computer_model'] as $field) {
            if (!empty($machine[$field])) {
                return (string) $machine[$field];
            }
        }

        return 'Dispositivo';
    }

    private static function validatedData(?int $ignoreId = null): array
    {
        $companyId = (int) ($_POST['company_id'] ?? 0);
        $deviceType = trim((string) ($_POST['device_type'] ?? 'notebook'));
        if (!array_key_exists($deviceType, Machine::deviceTypes())) {
            $deviceType = 'notebook';
        }

        $data = [
            'company_id' => $companyId,
            'device_type' => $deviceType,
            'equipment_name' => null,
            'tag' => self::nullable('tag'),
            'old_hostname' => self::nullable('old_hostname'),
            'new_hostname' => self::nullable('new_hostname'),
            'employee_name' => self::nullable('employee_name'),
            'department' => self::nullable('department'),
            'brand' => self::nullable('brand'),
            'computer_model' => self::nullable('computer_model'),
            'operating_system' => self::nullable('operating_system'),
            'machine_password' => self::nullable('machine_password'),
            'admin_user' => self::nullable('admin_user'),
            'admin_password' => self::nullable('admin_password'),
            'install_location' => self::nullable('install_location'),
            'modem_name' => self::nullable('modem_name'),
            'ip_address' => self::nullable('ip_address'),
            'gateway' => self::nullable('gateway'),
            'carrier' => self::nullable('carrier'),
            'printer_brand' => self::nullable('printer_brand'),
            'printer_connection_type' => self::nullable('printer_connection_type'),
            'printer_shared' => isset($_POST['printer_shared']) ? 1 : 0,
            'notes' => self::nullable('notes'),
            'tflux_installed' => isset($_POST['tflux_installed']) ? 1 : 0,
            'antivirus_installed' => isset($_POST['antivirus_installed']) ? 1 : 0,
            'requester_in_tflux' => isset($_POST['requester_in_tflux']) ? 1 : 0,
        ];

        $errors = [];
        foreach (self::requiredFieldsForType($deviceType) as $field) {
            if (($data[$field] ?? null) === null || $data[$field] === '') {
                $errors[$field] = 'Campo obrigatorio.';
            }
        }

        if (!Company::find($companyId)) {
            $errors['company_id'] = 'Empresa invalida.';
        }

        if (($data['tag'] ?? '') !== '' && Machine::duplicateExists($companyId, 'tag', (string) $data['tag'], $ignoreId)) {
            $errors['tag'] = 'Esta etiqueta ja existe nesta empresa.';
        }

        if (($data['new_hostname'] ?? '') !== '' && Machine::duplicateExists($companyId, 'new_hostname', (string) $data['new_hostname'], $ignoreId)) {
            $errors['new_hostname'] = 'Este hostname novo ja existe nesta empresa.';
        }

        return [$data, $errors];
    }

    private static function storePhotos(int $machineId, string $inputName = 'photos', string $photoType = 'general'): array
    {
        $stored = [];

        if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'][0])) {
            return $stored;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $total = count($_FILES[$inputName]['name']);

        for ($i = 0; $i < $total; $i++) {
            if ($_FILES[$inputName]['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($_FILES[$inputName]['error'][$i] !== UPLOAD_ERR_OK) {
                flash('danger', 'Uma das fotos nao pode ser enviada.');
                continue;
            }

            if ($_FILES[$inputName]['size'][$i] > MAX_UPLOAD_BYTES) {
                flash('danger', 'Uma foto excede o limite de 5MB.');
                continue;
            }

            $tmpName = $_FILES[$inputName]['tmp_name'][$i];
            $mime = $finfo->file($tmpName);

            if (!in_array($mime, ALLOWED_IMAGE_MIMES, true)) {
                flash('danger', 'Formato de imagem invalido. Use JPG, PNG ou WEBP.');
                continue;
            }

            $extension = 'jpg';
            if ($mime === 'image/png') {
                $extension = 'png';
            } elseif ($mime === 'image/webp') {
                $extension = 'webp';
            }
            $fileName = $machineId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
            $destination = UPLOAD_PATH . '/' . $fileName;

            if (move_uploaded_file($tmpName, $destination)) {
                $photoData = [
                    'machine_id' => $machineId,
                    'photo_type' => $photoType,
                    'photo_topic' => self::photoTopicForUpload($inputName, $i),
                    'location_name' => null,
                    'file_name' => $fileName,
                    'original_name' => basename((string) $_FILES[$inputName]['name'][$i]),
                    'mime_type' => $mime,
                    'file_size' => (int) $_FILES[$inputName]['size'][$i],
                ];
                MachinePhoto::create($photoData);
                $stored[] = $photoData;
            }
        }

        return $stored;
    }

    private static function photoTopicForUpload(string $inputName, int $index): string
    {
        $field = $inputName . '_topic';
        $topic = $_POST[$field] ?? $_POST['photo_topic'] ?? 'equipamento';

        if (is_array($topic)) {
            $topic = $topic[$index] ?? 'equipamento';
        }

        return self::normalizePhotoTopic((string) $topic);
    }

    private static function normalizePhotoTopic(string $topic): string
    {
        return array_key_exists($topic, MachinePhoto::topics()) ? $topic : 'equipamento';
    }

    private static function changedFields(array $old, array $new): array
    {
        $labels = [
            'tag' => 'Etiqueta',
            'device_type' => 'Tipo de dispositivo',
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
            'printer_brand' => 'Marca',
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
                $protected = in_array($field, ['machine_password', 'admin_password'], true);
                $changes['old'][$label] = $protected ? '[protegido]' : $oldValue;
                $changes['new'][$label] = $protected ? '[protegido]' : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }

    private static function sanitizeAuditData(array $data): array
    {
        if (array_key_exists('machine_password', $data)) {
            $data['machine_password'] = '[protegido]';
        }
        if (array_key_exists('admin_password', $data)) {
            $data['admin_password'] = '[protegido]';
        }

        return $data;
    }

    private static function nullable(string $field): ?string
    {
        $value = trim((string) ($_POST[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    private static function requiredFieldsForType(string $type): array
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
}
