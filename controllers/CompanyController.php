<?php

declare(strict_types=1);

class CompanyController
{
    public static function index(): void
    {
        require_admin();

        view('companies/index', [
            'title' => 'Empresas',
            'companies' => Company::all(),
        ]);
    }

    public static function create(): void
    {
        require_admin();

        view('companies/form', [
            'title' => 'Nova empresa',
            'company' => null,
            'errors' => [],
            'action' => '/?route=companies.store',
        ]);
    }

    public static function store(): void
    {
        require_admin();
        verify_csrf();

        [$data, $errors] = self::validatedData();

        if ($errors) {
            view('companies/form', [
                'title' => 'Nova empresa',
                'company' => $data,
                'errors' => $errors,
                'action' => '/?route=companies.store',
            ]);
            return;
        }

        $userId = (int) current_user()['id'];
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $companyId = Company::create($data);

        AuditLog::record([
            'action_type' => 'company_created',
            'affected_table' => 'companies',
            'affected_record_id' => $companyId,
            'company_id' => $companyId,
            'description' => 'Empresa cadastrada.',
            'new_data' => $data,
        ]);

        flash('success', 'Empresa cadastrada com sucesso.');
        redirect('/?route=companies.show&id=' . $companyId);
    }

    public static function show(): void
    {
        require_admin();
        $company = self::requireCompany();

        view('companies/show', [
            'title' => $company['name'],
            'company' => $company,
            'attachments' => CompanyAttachment::byCompany((int) $company['id']),
            'history' => AuditLog::byCompany((int) $company['id']),
        ]);
    }

    public static function edit(): void
    {
        require_admin();
        $company = self::requireCompany();

        view('companies/form', [
            'title' => 'Editar empresa',
            'company' => $company,
            'errors' => [],
            'action' => '/?route=companies.update&id=' . (int) $company['id'],
        ]);
    }

    public static function update(): void
    {
        require_admin();
        verify_csrf();
        $company = self::requireCompany();

        [$data, $errors] = self::validatedData((int) $company['id']);

        if ($errors) {
            view('companies/form', [
                'title' => 'Editar empresa',
                'company' => array_merge($company, $data),
                'errors' => $errors,
                'action' => '/?route=companies.update&id=' . (int) $company['id'],
            ]);
            return;
        }

        $data['updated_by'] = (int) current_user()['id'];
        $changes = self::changedFields($company, $data);
        Company::update((int) $company['id'], $data);

        if ($changes) {
            AuditLog::record([
                'action_type' => 'company_updated',
                'affected_table' => 'companies',
                'affected_record_id' => (int) $company['id'],
                'company_id' => (int) $company['id'],
                'description' => 'Empresa alterada.',
                'old_data' => $changes['old'],
                'new_data' => $changes['new'],
            ]);
        }

        flash('success', 'Empresa atualizada com sucesso.');
        redirect('/?route=companies.show&id=' . (int) $company['id']);
    }

    public static function deactivate(): void
    {
        require_admin();
        verify_csrf();
        $company = self::requireCompany();

        Company::deactivate((int) $company['id'], (int) current_user()['id']);
        AuditLog::record([
            'action_type' => 'company_deactivated',
            'affected_table' => 'companies',
            'affected_record_id' => (int) $company['id'],
            'company_id' => (int) $company['id'],
            'description' => 'Empresa desativada.',
            'old_data' => ['Status' => 'Ativa'],
            'new_data' => ['Status' => 'Inativa'],
        ]);

        flash('success', 'Empresa desativada.');
        redirect('/?route=companies.index');
    }

    public static function reactivate(): void
    {
        require_admin();
        verify_csrf();
        $company = self::requireCompany();

        Company::reactivate((int) $company['id'], (int) current_user()['id']);
        AuditLog::record([
            'action_type' => 'company_reactivated',
            'affected_table' => 'companies',
            'affected_record_id' => (int) $company['id'],
            'company_id' => (int) $company['id'],
            'description' => 'Empresa reativada.',
            'old_data' => ['Status' => 'Inativa'],
            'new_data' => ['Status' => 'Ativa'],
        ]);

        flash('success', 'Empresa reativada.');
        redirect('/?route=companies.show&id=' . (int) $company['id']);
    }

    public static function destroy(): void
    {
        require_admin();
        verify_csrf();
        $company = self::requireCompany();

        try {
            Company::delete((int) $company['id']);
        } catch (Throwable $exception) {
            flash('danger', 'Não foi possível excluir totalmente. Remova ou desative os registros vinculados primeiro.');
            redirect('/?route=companies.show&id=' . (int) $company['id']);
        }

        AuditLog::record([
            'action_type' => 'company_deleted',
            'affected_table' => 'companies',
            'affected_record_id' => (int) $company['id'],
            'description' => 'Empresa excluída definitivamente.',
            'old_data' => self::auditCompanyData($company),
        ]);

        flash('success', 'Empresa excluída definitivamente.');
        redirect('/?route=companies.index');
    }

    public static function storeAttachment(): void
    {
        require_admin();
        verify_csrf();

        $company = self::requireCompanyById((int) ($_POST['company_id'] ?? 0));
        $categoryId = self::validatedAttachmentCategoryId();
        $redirectUrl = self::attachmentRedirectUrl((int) $company['id'], $categoryId);

        if (empty($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            flash('danger', 'Selecione um arquivo para anexar.');
            redirect($redirectUrl);
        }

        $file = $_FILES['attachment'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash('danger', 'Selecione um arquivo para anexar.');
            redirect($redirectUrl);
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            flash('danger', 'Não foi possível enviar o anexo.');
            redirect($redirectUrl);
        }

        $originalName = safe_original_filename((string) ($file['name'] ?? 'arquivo'));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'zip'];

        if (!in_array($extension, $allowedExtensions, true)) {
            flash('danger', 'Tipo de arquivo não permitido.');
            redirect($redirectUrl);
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > COMPANY_ATTACHMENT_MAX_BYTES) {
            flash('danger', 'O anexo deve ter no máximo ' . format_file_size(COMPANY_ATTACHMENT_MAX_BYTES) . '.');
            redirect($redirectUrl);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            flash('danger', 'Upload inválido.');
            redirect($redirectUrl);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmpName) ?: 'application/octet-stream');
        if (!self::isAttachmentMimeAllowed($extension, $mime, $tmpName)) {
            flash('danger', 'Tipo de arquivo não permitido.');
            redirect($redirectUrl);
        }

        $directory = self::attachmentDirectory();
        $diskName = (int) $company['id'] . '-' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $directory . DIRECTORY_SEPARATOR . $diskName;

        if (!move_uploaded_file($tmpName, $destination)) {
            flash('danger', 'Não foi possível salvar o anexo.');
            redirect($redirectUrl);
        }

        $attachmentId = CompanyAttachment::create([
            'company_id' => (int) $company['id'],
            'category_id' => $categoryId,
            'disk_name' => $diskName,
            'original_name' => $originalName,
            'mime_type' => $mime,
            'file_size' => $size,
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'uploaded_by' => (int) current_user()['id'],
        ]);

        AuditLog::record([
            'action_type' => 'company_attachment_added',
            'affected_table' => 'company_attachments',
            'affected_record_id' => $attachmentId,
            'company_id' => (int) $company['id'],
            'description' => 'Anexo adicionado à empresa.',
            'new_data' => [
                'Arquivo' => $originalName,
                'Categoria ID' => $categoryId,
                'Tamanho' => format_file_size($size),
            ],
        ]);

        flash('success', 'Anexo enviado com sucesso.');
        redirect($redirectUrl);
    }

    public static function downloadAttachment(): void
    {
        require_admin();

        $attachment = self::requireAttachment((int) ($_GET['id'] ?? 0));
        $path = self::attachmentPath((string) $attachment['disk_name']);

        if ($path === null || !is_file($path)) {
            http_response_code(404);
            view('errors/404', ['title' => 'Anexo não encontrado']);
            exit;
        }

        $downloadName = safe_download_filename((string) $attachment['original_name']);
        AuditLog::record([
            'action_type' => 'company_attachment_downloaded',
            'affected_table' => 'company_attachments',
            'affected_record_id' => (int) $attachment['id'],
            'company_id' => (int) $attachment['company_id'],
            'description' => 'Anexo baixado da empresa.',
            'new_data' => [
                'Arquivo' => $attachment['original_name'] ?? null,
                'Categoria ID' => $attachment['category_id'] ?? null,
                'Tamanho' => format_file_size((int) ($attachment['file_size'] ?? 0)),
                'Tipo' => $attachment['mime_type'] ?? null,
            ],
        ]);

        header('Content-Type: ' . ((string) $attachment['mime_type'] ?: 'application/octet-stream'));
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public static function deleteAttachment(): void
    {
        require_admin();
        verify_csrf();

        $attachment = self::requireAttachment((int) ($_POST['id'] ?? 0));
        $path = self::attachmentPath((string) $attachment['disk_name']);

        if ($path !== null && is_file($path)) {
            unlink($path);
        }

        CompanyAttachment::delete((int) $attachment['id']);
        AuditLog::record([
            'action_type' => 'company_attachment_deleted',
            'affected_table' => 'company_attachments',
            'affected_record_id' => (int) $attachment['id'],
            'company_id' => (int) $attachment['company_id'],
            'description' => 'Anexo removido da empresa.',
            'old_data' => [
                'Arquivo' => $attachment['original_name'] ?? null,
                'Tamanho' => format_file_size((int) ($attachment['file_size'] ?? 0)),
            ],
        ]);

        flash('success', 'Anexo removido.');
        redirect(self::attachmentRedirectUrl((int) $attachment['company_id'], isset($attachment['category_id']) ? (int) $attachment['category_id'] : null));
    }

    private static function requireCompany(): array
    {
        return self::requireCompanyById((int) ($_GET['id'] ?? 0));
    }

    private static function requireCompanyById(int $companyId): array
    {
        $company = Company::find($companyId);
        if (!$company) {
            http_response_code(404);
            view('errors/404', ['title' => 'Empresa não encontrada']);
            exit;
        }

        return $company;
    }

    private static function requireAttachment(int $attachmentId): array
    {
        $attachment = CompanyAttachment::find($attachmentId);
        if (!$attachment || empty($attachment['company_is_active'])) {
            http_response_code(404);
            view('errors/404', ['title' => 'Anexo não encontrado']);
            exit;
        }

        return $attachment;
    }

    private static function attachmentDirectory(): string
    {
        $directory = STORAGE_PATH . DIRECTORY_SEPARATOR . 'company_attachments';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }

    private static function attachmentPath(string $diskName): ?string
    {
        $diskName = trim($diskName);
        if ($diskName === '' || basename($diskName) !== $diskName || preg_match('/[\\\\\/\x00-\x1F\x7F]/', $diskName)) {
            return null;
        }

        $directory = realpath(self::attachmentDirectory());
        if ($directory === false) {
            return null;
        }

        $path = $directory . DIRECTORY_SEPARATOR . $diskName;
        $pathDirectory = realpath(dirname($path));
        if ($pathDirectory === false || $pathDirectory !== $directory) {
            return null;
        }

        return $path;
    }

    private static function validatedAttachmentCategoryId(): ?int
    {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        if ($categoryId <= 0) {
            return null;
        }

        $category = VaultCategory::find($categoryId);
        return $category && !empty($category['is_active']) ? $categoryId : null;
    }

    private static function isAttachmentMimeAllowed(string $extension, string $mime, string $path): bool
    {
        $extension = strtolower($extension);
        $mime = strtolower($mime);

        if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $allowedImageMimes = [
                'jpg' => ['image/jpeg'],
                'jpeg' => ['image/jpeg'],
                'png' => ['image/png'],
                'webp' => ['image/webp'],
            ];

            return in_array($mime, $allowedImageMimes[$extension] ?? [], true)
                && @getimagesize($path) !== false;
        }

        if ($extension === 'pdf') {
            return strncmp((string) file_get_contents($path, false, null, 0, 4), '%PDF', 4) === 0;
        }

        if (in_array($extension, ['docx', 'xlsx', 'zip'], true)) {
            return strncmp((string) file_get_contents($path, false, null, 0, 4), "PK\x03\x04", 4) === 0;
        }

        if (in_array($extension, ['doc', 'xls'], true)) {
            return bin2hex((string) file_get_contents($path, false, null, 0, 8)) === 'd0cf11e0a1b11ae1';
        }

        if (in_array($extension, ['csv', 'txt'], true)) {
            $sample = (string) file_get_contents($path, false, null, 0, 512);
            return strpos($sample, "\0") === false;
        }

        $allowedMimes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/cdfv2'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip'],
            'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/cdfv2'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip'],
            'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip', 'application/x-zip', 'application/x-zip-compressed'],
        ];

        $blockedMimes = ['application/x-php', 'text/x-php', 'text/html', 'application/x-msdownload'];
        if (in_array($mime, $blockedMimes, true)) {
            return false;
        }

        return in_array($mime, $allowedMimes[$extension] ?? [], true);
    }

    private static function attachmentRedirectUrl(int $companyId, ?int $categoryId): string
    {
        if ($categoryId !== null && $categoryId > 0) {
            $category = VaultCategory::find($categoryId);
            if ($category && !empty($category['parent_id'])) {
                return '/?route=vault.show&id=' . $companyId . '&category_id=' . $categoryId;
            }

            return '/?route=vault.show&id=' . $companyId . '&parent_id=' . $categoryId;
        }

        return '/?route=companies.show&id=' . $companyId;
    }

    private static function validatedData(?int $ignoreId = null): array
    {
        $data = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'tag_pattern' => trim((string) ($_POST['tag_pattern'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        $errors = [];
        if ($data['name'] === '') {
            $errors['name'] = 'Campo obrigatório.';
        } elseif (strlen($data['name']) > 160) {
            $errors['name'] = 'Deve ter no máximo 160 caracteres.';
        } elseif (Company::duplicateNameExists($data['name'], $ignoreId)) {
            $errors['name'] = 'Ja existe uma empresa com este nome.';
        }

        if (strlen($data['tag_pattern']) > 160) {
            $errors['tag_pattern'] = 'Deve ter no máximo 160 caracteres.';
        }

        return [$data, $errors];
    }

    private static function changedFields(array $old, array $new): array
    {
        $labels = [
            'name' => 'Nome da empresa',
            'tag_pattern' => 'Padrão de etiqueta',
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

    private static function auditCompanyData(array $company): array
    {
        return [
            'id' => (int) ($company['id'] ?? 0),
            'name' => $company['name'] ?? null,
            'tag_pattern' => $company['tag_pattern'] ?? null,
            'is_active' => !empty($company['is_active']),
        ];
    }
}
