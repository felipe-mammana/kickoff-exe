<?php

declare(strict_types=1);

class VaultController
{
    public static function export(): void
    {
        require_permission('vault.export');
        require_vault_access();
        if (!is_post()) { http_response_code(405); return; }
        verify_csrf();
        $company = self::requireCompany((int) ($_POST['company_id'] ?? 0));
        $actor = User::find((int) current_user()['id']);
        if (!PasswordSecurity::confirm($actor, (string) ($_POST['password'] ?? ''))) {
            flash('danger', 'Senha incorreta ou limite de tentativas atingido.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }
        $stmt = db()->prepare("SELECT id, company_id, category_id, title, username, service_url,
                                      notes, custom_fields, operational_state, vault_version,
                                      vault_operation_id, lock_version, is_active
                               FROM vault_credentials
                               WHERE company_id = ? AND is_active = 1 AND operational_state = 'ready'
                               ORDER BY id LIMIT 5001");
        $stmt->execute([(int) $company['id']]);
        $rows = $stmt->fetchAll();
        if (count($rows) > 5000) {
            flash('danger', 'Exportacao excede o limite de 5000 credenciais. Nenhum arquivo foi gerado.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }
        try {
            $service = self::secretService();
            foreach ($rows as &$row) {
                $row = VaultCredential::decryptMetadata($row);
                $row['secret_value'] = $service->reveal($row);
            }
            unset($row);
        } catch (VaultCredentialServiceException $error) {
            flash('danger', 'Exportacao cancelada: ' . $error->getMessage() . ' Nenhum arquivo foi gerado.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }
        $payload = EncryptedBackup::encrypt(json_encode(['company_id' => (int) $company['id'], 'exported_at' => gmdate('c'), 'credentials' => $rows], JSON_THROW_ON_ERROR), 'vault');
        try {
            AuditLog::record(['required' => true, 'action_type' => 'vault_exported', 'affected_table' => 'vault_credentials',
                'company_id' => (int) $company['id'], 'description' => 'Exportacao criptografada do cofre.',
                'new_data' => ['count' => count($rows)]]);
        } catch (Throwable) {
            flash('danger', 'Exportacao cancelada porque a auditoria obrigatoria esta indisponivel.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }
        header('Cache-Control: no-store, private, max-age=0');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="cofre-' . (int) $company['id'] . '.exe-vault"');
        header('X-Content-Type-Options: nosniff');
        echo $payload;
        exit;
    }

    public static function index(): void
    {
        require_permission('vault.view');
        require_vault_access();

        $searchMode = (string) ($_GET['search_mode'] ?? 'company');
        if (!in_array($searchMode, ['company', 'credential'], true)) {
            $searchMode = 'company';
        }

        $filters = [
            'search_mode' => $searchMode,
            'company_id' => 0,
            'category_id' => 0,
            'query' => trim((string) ($_GET['query'] ?? '')),
        ];

        $accessibleCompanies = self::accessibleCompanies();
        $credentialCategories = VaultCategory::selectableForCompanies(array_column($accessibleCompanies, 'id'));

        view('vault/index', [
            'title' => 'Cofre de senhas',
            'filters' => $filters,
            'companies' => VaultCredential::companiesSummary($filters),
            'credentials' => VaultCredential::filtered($filters),
            'accessibleCompanies' => $accessibleCompanies,
            'credentialCategories' => $credentialCategories,
            'vaultCustomFieldConfig' => self::customFieldConfig($credentialCategories),
        ]);
    }

    public static function show(): void
    {
        require_permission('vault.view');
        require_vault_access();

        $companyId = (int) ($_GET['id'] ?? 0);
        $company = Company::find($companyId);

        if (!$company || empty($company['is_active'])) {
            DashboardController::notFound();
            return;
        }

        $parentId = (int) ($_GET['parent_id'] ?? 0);
        $categoryId = (int) ($_GET['category_id'] ?? 0);
        $selectedParent = $parentId > 0 ? VaultCategory::find($parentId) : null;
        $selectedCategory = $categoryId > 0 ? VaultCategory::find($categoryId) : null;
        if ($selectedParent && !VaultCategory::isSelectableForCompany($selectedParent, $companyId)) {
            $selectedParent = null;
        }
        if ($selectedCategory && !VaultCategory::isSelectableForCompany($selectedCategory, $companyId)) {
            $selectedCategory = null;
            $categoryId = 0;
        }
        if ($selectedCategory && !empty($selectedCategory['parent_id'])) {
            $selectedParent = VaultCategory::find((int) $selectedCategory['parent_id']);
            if ($selectedParent && (empty($selectedParent['is_active']) || !VaultCategory::isVisibleForCompany($selectedParent, $companyId))) {
                $selectedParent = null;
            }
        }

        $effectiveCategoryId = $categoryId;
        if ($effectiveCategoryId <= 0 && $selectedParent) {
            $effectiveCategoryId = (int) $selectedParent['id'];
        }

        $filters = [
            'company_id' => $companyId,
            'category_id' => $effectiveCategoryId,
            'query' => trim((string) ($_GET['query'] ?? '')),
        ];

        $categories = VaultCategory::allWithCounts($companyId);
        $accessibleCompanies = self::accessibleCompanies();
        $credentialCategories = VaultCategory::selectableForCompanies(array_column($accessibleCompanies, 'id'));

        view('vault/show', [
            'title' => 'Cofre - ' . $company['name'],
            'company' => $company,
            'filters' => $filters,
            'categoryErrors' => $_SESSION['vault_category_errors'] ?? [],
            'categoryOld' => $_SESSION['vault_category_old'] ?? [],
            'openCategoryModal' => (string) ($_GET['open_category_modal'] ?? ''),
            'totalCredentials' => VaultCredential::countFiltered([
                'company_id' => $companyId,
                'query' => $filters['query'],
            ]),
            'credentials' => VaultCredential::filtered($filters),
            'attachments' => $effectiveCategoryId > 0 ? CompanyAttachment::byCompanyAndCategory($companyId, $effectiveCategoryId) : [],
            'attachmentCategoryId' => $effectiveCategoryId > 0 ? $effectiveCategoryId : null,
            'categories' => $categories,
            'accessibleCompanies' => $accessibleCompanies,
            'credentialCategories' => $credentialCategories,
            'vaultCustomFieldConfig' => self::customFieldConfig($credentialCategories),
            'rootCategories' => VaultCategory::withCountsByParent(null, $companyId),
            'childCategories' => $selectedParent ? VaultCategory::withCountsByParent((int) $selectedParent['id'], $companyId) : [],
            'selectedParent' => $selectedParent,
            'selectedCategory' => $selectedCategory,
            'iconOptions' => VaultCategory::iconOptions(),
            'vaultSettings' => AppSetting::vaultSettings(),
        ]);
        unset($_SESSION['vault_category_errors'], $_SESSION['vault_category_old']);
    }

    public static function store(): void
    {
        require_permission('vault.create');
        require_vault_access();
        verify_csrf();

        $companyId = (int) ($_POST['company_id'] ?? 0);
        $company = self::findAccessibleCompany($companyId);
        if (!$company) {
            $_SESSION['vault_form_errors'] = ['company_id' => 'Selecione uma empresa válida.'];
            $_SESSION['vault_form_old'] = self::submittedCreateOldData();
            flash('danger', 'Selecione uma empresa válida para cadastrar a credencial.');
            redirect('/?route=vault.index&open_modal=create');
        }
        [$data, $errors] = self::validatedData($company);

        if ($errors) {
            $_SESSION['vault_form_errors'] = $errors;
            $_SESSION['vault_form_old'] = self::oldData($data);
            flash('danger', 'Revise os campos da credencial.');
            $route = (string) ($_POST['form_context'] ?? '') === 'index'
                ? '/?route=vault.index&open_modal=create'
                : '/?route=vault.show&id=' . (int) $company['id'] . '&open_modal=create';
            redirect($route);
        }

        $userId = (int) current_user()['id'];
        $secret = (string) $data['secret_value'];
        unset($data['secret_value']);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        try {
            $credentialId = self::secretService()->create($data, $secret);
        } catch (VaultCredentialServiceException $error) {
            flash('danger', $error->getMessage());
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }

        try {
            AuditLog::record([
                'required' => true,
                'action_type' => 'vault_credential_created',
                'affected_table' => 'vault_credentials',
                'affected_record_id' => $credentialId,
                'company_id' => (int) $company['id'],
                'description' => 'Credencial do cofre cadastrada.',
                'new_data' => self::auditData($data),
            ]);
        } catch (Throwable) {
            flash('danger', 'A credencial foi gravada, mas a auditoria obrigatoria falhou. Nao repita o cadastro; acione o suporte.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }

        flash('success', 'Credencial cadastrada com sucesso.');
        redirect('/?route=vault.show&id=' . (int) $company['id']);
    }

    public static function update(): void
    {
        require_permission('vault.edit');
        require_vault_access();
        verify_csrf();

        $credential = self::requireCredential((int) ($_POST['id'] ?? 0));
        $company = self::requireCompany((int) $credential['company_id']);
        [$data, $errors] = self::validatedData($company, $credential);

        if ($errors) {
            $_SESSION['vault_form_errors'] = $errors;
            $_SESSION['vault_form_old'] = array_merge(self::oldData($data), [
                'id' => (int) $credential['id'],
                'lock_version' => (int) $credential['lock_version'],
            ]);
            flash('danger', 'Revise os campos da credencial.');
            redirect('/?route=vault.show&id=' . (int) $company['id'] . '&open_modal=edit');
        }

        $secret = $data['secret_value'] !== null ? (string) $data['secret_value'] : null;
        $data['updated_by'] = (int) current_user()['id'];
        unset($data['company_id'], $data['created_by'], $data['secret_value'], $data['is_active']);

        $changes = self::changedFields($credential, $data);
        if ($secret !== null && $secret !== '') {
            $changes['old']['Senha'] = '[protegido]';
            $changes['new']['Senha'] = '[protegido]';
        }
        if (!$changes) {
            flash('info', 'Nenhuma alteracao foi informada.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }
        $submittedLockVersion = filter_var($_POST['lock_version'] ?? null, FILTER_VALIDATE_INT);
        if ($submittedLockVersion === false || $submittedLockVersion < 0) {
            flash('danger', 'A versao da credencial e invalida. Reabra o formulario.');
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }
        try {
            self::secretService()->update(
                (int) $credential['id'],
                $data,
                $secret,
                (int) $submittedLockVersion
            );
        } catch (VaultCredentialServiceException $error) {
            flash('danger', $error->getMessage());
            redirect('/?route=vault.show&id=' . (int) $company['id']);
        }

        if ($changes) {
            try {
                AuditLog::record([
                    'required' => true,
                    'action_type' => 'vault_credential_updated',
                    'affected_table' => 'vault_credentials',
                    'affected_record_id' => (int) $credential['id'],
                    'company_id' => (int) $company['id'],
                    'description' => 'Credencial do cofre alterada.',
                    'old_data' => $changes['old'],
                    'new_data' => $changes['new'],
                ]);
            } catch (Throwable) {
                flash('danger', 'A credencial foi alterada, mas a auditoria obrigatoria falhou. Nao repita a operacao; acione o suporte.');
                redirect('/?route=vault.show&id=' . (int) $company['id']);
            }
        }

        flash('success', 'Credencial atualizada com sucesso.');
        redirect('/?route=vault.show&id=' . (int) $company['id']);
    }

    public static function deactivate(): void
    {
        require_permission('vault.delete');
        require_vault_access();
        verify_csrf();

        $credential = self::requireCredential((int) ($_POST['id'] ?? 0));
        $submittedLockVersion = filter_var($_POST['lock_version'] ?? null, FILTER_VALIDATE_INT);
        if ($submittedLockVersion === false || $submittedLockVersion < 0) {
            flash('danger', 'A versao da credencial e invalida. Atualize a pagina.');
            redirect('/?route=vault.show&id=' . (int) $credential['company_id']);
        }
        try {
            self::secretService()->deactivate(
                (int) $credential['id'],
                (int) current_user()['id'],
                (int) $submittedLockVersion
            );
        } catch (VaultCredentialServiceException $error) {
            flash('danger', $error->getMessage());
            redirect('/?route=vault.show&id=' . (int) $credential['company_id']);
        }

        try {
            AuditLog::record([
                'required' => true,
                'action_type' => 'vault_credential_deactivated',
                'affected_table' => 'vault_credentials',
                'affected_record_id' => (int) $credential['id'],
                'company_id' => (int) $credential['company_id'],
                'description' => 'Credencial do cofre desativada.',
                'old_data' => ['Status' => 'Ativa'],
                'new_data' => ['Status' => 'Inativa'],
            ]);
        } catch (Throwable) {
            flash('danger', 'A credencial foi desativada, mas a auditoria obrigatoria falhou. Nao repita a operacao; acione o suporte.');
            redirect('/?route=vault.show&id=' . (int) $credential['company_id']);
        }

        flash('success', 'Credencial desativada.');
        redirect('/?route=vault.show&id=' . (int) $credential['company_id']);
    }

    public static function reveal(): void
    {
        require_permission('vault.reveal');
        if (($_POST['intent'] ?? '') === 'copy') {
            require_permission('vault.copy');
            if (empty(AppSetting::vaultSettings()['allow_password_copy'])) ApiResponse::error('forbidden', 'Copia desativada.', 403);
        }
        require_vault_access();

        if (!is_post()) {
            ApiResponse::error('method_not_allowed', 'Método não permitido.', 405);
        }
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');

        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !csrf_token_is_valid($token)) {
            ApiResponse::error('csrf_required', 'Sessão expirada. Atualize a página e tente novamente.', 419);
        }

        $currentUser = User::find((int) current_user()['id']);
        if (!$currentUser) {
            ApiResponse::error('auth_required', 'Sessão inválida.', 401);
        }

        if (!empty($currentUser['vault_require_password_reveal'])) {
            $password = (string) ($_POST['reveal_password'] ?? '');
            if ($password === '') {
                ApiResponse::error('password_required', 'Confirme sua senha para revelar esta credencial.', 403);
            }

            if (!PasswordSecurity::confirm($currentUser, $password)) {
                AuditLog::record([
                    'action_type' => 'vault_credential_reveal_password_failed',
                    'affected_table' => 'users',
                    'affected_record_id' => (int) $currentUser['id'],
                    'description' => 'Senha incorreta ao tentar revelar credencial do cofre.',
                ]);
                ApiResponse::error('password_invalid', 'Senha inválida.', 403);
            }
        }

        if (AppSetting::vaultRequiresRevealConfirmation() && empty($_POST['confirm_reveal'])) {
            ApiResponse::error('confirmation_required', 'Confirme a revelação da senha para continuar.', 403);
        }

        $credential = self::requireCredential((int) ($_POST['id'] ?? 0));
        $rateLimit = SecurityRateLimit::hit('vault-reveal', (int) $currentUser['id'], 30, 60);
        if (!$rateLimit['allowed']) {
            header('Retry-After: ' . $rateLimit['retry_after']);
            ApiResponse::error('rate_limited', 'Limite de revelações excedido. Aguarde antes de tentar novamente.', 429);
        }
        try {
            $value = self::secretService()->reveal($credential);
        } catch (VaultCredentialServiceException $error) {
            $status = $error->isConflict() ? 409 : 503;
            ApiResponse::error($error->reason(), $error->getMessage(), $status);
        }

        try {
            AuditLog::record([
                'action_type' => 'vault_credential_revealed',
                'required' => true,
                'affected_table' => 'vault_credentials',
                'affected_record_id' => (int) $credential['id'],
                'company_id' => (int) $credential['company_id'],
                'description' => 'Usuario revelou credencial do cofre.',
                'new_data' => [
                    'credential_id' => (int) $credential['id'],
                    'title' => $credential['title'] ?? null,
                    'company_id' => (int) $credential['company_id'],
                    'category_id' => $credential['category_id'] ?? null,
                    'category_name' => $credential['category_name'] ?? null,
                    'username' => $credential['username'] ?? null,
                    'secret_value' => '[protegido]',
                ],
            ]);
        } catch (Throwable) {
            ApiResponse::error('audit_unavailable', 'Auditoria indisponivel. O segredo nao foi devolvido.', 503);
        }
        VaultCredential::markRevealed((int) $credential['id'], (int) current_user()['id']);

        ApiResponse::ok([
            'id' => (int) $credential['id'],
            'title' => $credential['title'],
            'value' => $value,
        ]);
    }

    public static function storeCategory(): void
    {
        require_permission('vault.configure');
        require_vault_access();
        verify_csrf();

        $company = self::requireCompany((int) ($_POST['company_id'] ?? 0));
        [$data, $errors] = self::validatedCategoryData((int) $company['id']);

        if ($errors) {
            $_SESSION['vault_category_errors'] = $errors;
            $_SESSION['vault_category_old'] = $data;
            flash('danger', 'Revise os campos da categoria.');
            $modal = !empty($data['parent_id']) ? 'subcategory' : 'category';
            redirect('/?route=vault.show&id=' . (int) $company['id'] . '&open_category_modal=' . $modal);
        }

        $userId = (int) current_user()['id'];
        $data['company_id'] = (int) $company['id'];
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $categoryId = VaultCategory::create($data);

        AuditLog::record([
            'action_type' => 'vault_category_created',
            'affected_table' => 'vault_categories',
            'affected_record_id' => $categoryId,
            'company_id' => (int) $company['id'],
            'description' => 'Categoria do cofre cadastrada.',
            'new_data' => $data,
        ]);

        flash('success', 'Categoria cadastrada com sucesso.');
        $redirect = '/?route=vault.show&id=' . (int) $company['id'];
        if (!empty($data['parent_id'])) {
            $redirect .= '&parent_id=' . (int) $data['parent_id'];
        }
        redirect($redirect);
    }

    private static function requireCompany(int $companyId): array
    {
        $company = self::findAccessibleCompany($companyId);

        if (!$company || empty($company['is_active'])) {
            http_response_code(404);
            view('errors/404', ['title' => 'Empresa não encontrada']);
            exit;
        }

        return $company;
    }

    private static function accessibleCompanies(): array
    {
        return Company::all(true);
    }

    private static function findAccessibleCompany(int $companyId): ?array
    {
        if ($companyId <= 0) {
            return null;
        }

        foreach (self::accessibleCompanies() as $company) {
            if ((int) $company['id'] === $companyId) {
                return $company;
            }
        }

        return null;
    }

    private static function requireCredential(int $credentialId): array
    {
        $credential = VaultCredential::find($credentialId);

        if (!$credential || empty($credential['is_active']) || ($credential['operational_state'] ?? '') !== 'ready') {
            http_response_code(404);
            view('errors/404', ['title' => 'Credencial não encontrada']);
            exit;
        }

        return $credential;
    }

    private static function validatedData(array $company, ?array $current = null): array
    {
        $categoryId = (int) ($_POST['category_id'] ?? 0);
        $secret = trim((string) ($_POST['secret_value'] ?? ''));
        $data = [
            'company_id' => (int) $company['id'],
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'title' => trim((string) ($_POST['title'] ?? '')),
            'service_url' => self::nullable('service_url'),
            'username' => self::nullable('username'),
            'secret_value' => $secret !== '' ? $secret : null,
            'notes' => self::nullable('notes'),
            'custom_fields' => null,
            'is_active' => 1,
        ];

        $errors = [];

        if ($data['title'] === '') {
            $errors['title'] = 'Campo obrigatório.';
        } elseif (strlen($data['title']) > 160) {
            $errors['title'] = 'Deve ter no máximo 160 caracteres.';
        }

        $category = $data['category_id'] !== null ? VaultCategory::find((int) $data['category_id']) : null;
        if ($data['category_id'] !== null && (!$category || !VaultCategory::isSelectableForCompany($category, (int) $company['id']))) {
            $errors['category_id'] = 'Tipo de credencial inválido.';
        }

        if ($current === null && ($data['secret_value'] === null || $data['secret_value'] === '')) {
            $errors['secret_value'] = 'Campo obrigatório.';
        } elseif ($data['secret_value'] !== null && strlen((string) $data['secret_value']) > 5000) {
            $errors['secret_value'] = 'Deve ter no máximo 5000 caracteres.';
        }

        if ($data['service_url'] !== null && strlen($data['service_url']) > 255) {
            $errors['service_url'] = 'Deve ter no máximo 255 caracteres.';
        }

        if ($data['username'] !== null && strlen($data['username']) > 190) {
            $errors['username'] = 'Deve ter no máximo 190 caracteres.';
        }

        if ($data['notes'] !== null && strlen($data['notes']) > 5000) {
            $errors['notes'] = 'Deve ter no máximo 5000 caracteres.';
        }

        [$customFields, $customFieldErrors] = self::validatedCustomFields($data['category_id']);
        $data['custom_fields'] = $customFields ? json_encode($customFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        $errors += $customFieldErrors;

        return [$data, $errors];
    }

    private static function validatedCategoryData(int $companyId): array
    {
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $icon = trim((string) ($_POST['icon'] ?? 'folder'));
        $data = [
            'parent_id' => $parentId > 0 ? $parentId : null,
            'name' => $name,
            'slug' => self::uniqueCategorySlug($name),
            'description' => self::nullable('description'),
            'icon' => array_key_exists($icon, VaultCategory::iconOptions()) ? $icon : 'folder',
            'is_active' => 1,
        ];
        $errors = [];

        if ($data['name'] === '') {
            $errors['name'] = 'Campo obrigatório.';
        } elseif (strlen($data['name']) > 120) {
            $errors['name'] = 'Deve ter no máximo 120 caracteres.';
        }

        if ($data['description'] !== null && strlen($data['description']) > 255) {
            $errors['description'] = 'Deve ter no máximo 255 caracteres.';
        }

        $parent = $data['parent_id'] !== null ? VaultCategory::find((int) $data['parent_id']) : null;
        if ($data['parent_id'] !== null && (!$parent || empty($parent['is_active']) || !VaultCategory::isVisibleForCompany($parent, $companyId))) {
            $errors['parent_id'] = 'Categoria pai inválida.';
        }

        return [$data, $errors];
    }

    private static function uniqueCategorySlug(string $name): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $base = $base !== '' ? substr($base, 0, 110) : 'categoria';
        $slug = $base;
        $suffix = 2;

        while (VaultCategory::slugExists($slug)) {
            $slug = substr($base, 0, 104) . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function nullable(string $field): ?string
    {
        $value = trim((string) ($_POST[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    private static function oldData(array $data): array
    {
        if (isset($data['custom_fields']) && is_string($data['custom_fields'])) {
            $decoded = json_decode($data['custom_fields'], true);
            $data['custom_fields'] = is_array($decoded) ? $decoded : [];
        }

        unset($data['secret_value']);

        return $data;
    }

    private static function submittedCreateOldData(): array
    {
        return [
            'company_id' => (int) ($_POST['company_id'] ?? 0),
            'category_id' => (int) ($_POST['category_id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'service_url' => self::nullable('service_url'),
            'username' => self::nullable('username'),
            'notes' => self::nullable('notes'),
            'custom_fields' => is_array($_POST['custom_fields'] ?? null) ? $_POST['custom_fields'] : [],
        ];
    }

    private static function auditData(array $data): array
    {
        $clean = $data;
        foreach (['notes', 'custom_fields'] as $field) {
            if (array_key_exists($field, $clean)) $clean[$field] = '[protegido]';
        }
        if (array_key_exists('secret_value', $clean)) {
            $clean['secret_value'] = '[protegido]';
        }

        return $clean;
    }

    private static function changedFields(array $old, array $new): array
    {
        $labels = [
            'category_id' => 'Tipo',
            'title' => 'Nome',
            'service_url' => 'URL',
            'username' => 'Usuário',
            'notes' => 'Observações',
            'custom_fields' => 'Campos personalizados',
        ];

        $changes = ['old' => [], 'new' => []];
        foreach ($labels as $field => $label) {
            $oldValue = (string) ($old[$field] ?? '');
            $newValue = (string) ($new[$field] ?? '');

            if ($oldValue !== $newValue) {
                $protected = in_array($field, ['notes', 'custom_fields'], true);
                $changes['old'][$label] = $protected ? '[protegido]' : $oldValue;
                $changes['new'][$label] = $protected ? '[protegido]' : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }

    private static function customFieldConfig(array $categories): array
    {
        $config = [];

        foreach ($categories as $category) {
            $categoryId = (int) ($category['id'] ?? 0);
            if ($categoryId <= 0) {
                continue;
            }

            $fields = self::customFieldDefinitionsForCategory($categoryId);
            if ($fields) {
                $config[(string) $categoryId] = $fields;
            }
        }

        return $config;
    }

    private static function validatedCustomFields(?int $categoryId): array
    {
        $definitions = self::customFieldDefinitionsForCategory($categoryId);
        $input = $_POST['custom_fields'] ?? [];
        $input = is_array($input) ? $input : [];
        $values = [];
        $errors = [];

        foreach ($definitions as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $label = (string) ($field['label'] ?? 'Campo');
            $type = (string) ($field['type'] ?? 'text');
            $value = trim((string) ($input[$key] ?? ''));

            if (!empty($field['required']) && $value === '') {
                $errors['custom_fields'] = 'Preencha os campos personalizados obrigatórios.';
                continue;
            }

            if ($value === '') {
                continue;
            }

            $maxLength = $type === 'textarea' ? 5000 : 255;
            if (strlen($value) > $maxLength) {
                $errors['custom_fields'] = $label . ' deve ter no máximo ' . $maxLength . ' caracteres.';
                continue;
            }

            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors['custom_fields'] = $label . ' deve ser um e-mail válido.';
                continue;
            }

            if ($type === 'url' && filter_var($value, FILTER_VALIDATE_URL) === false) {
                $errors['custom_fields'] = $label . ' deve ser uma URL válida.';
                continue;
            }

            if ($type === 'number') {
                $number = str_replace(',', '.', $value);
                if (!is_numeric($number)) {
                    $errors['custom_fields'] = $label . ' deve ser um número válido.';
                    continue;
                }
                $value = $number;
            }

            $values[$key] = $value;
        }

        return [$values, $errors];
    }

    private static function customFieldDefinitionsForCategory(?int $categoryId): array
    {
        if ($categoryId === null || $categoryId <= 0) {
            return [];
        }

        $category = VaultCategory::find($categoryId);
        if (!$category || empty($category['is_active'])) {
            return [];
        }

        $settings = AppSetting::vaultSettings();

        return AppSetting::vaultFieldsForCategory($category, $settings);
    }

    private static function secretService(): VaultCredentialSecretService
    {
        return VaultCredentialSecretService::fromApplication();
    }
}
