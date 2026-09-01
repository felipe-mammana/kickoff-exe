<?php

declare(strict_types=1);

class VaultController
{
    public static function index(): void
    {
        require_admin();

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

        view('vault/index', [
            'title' => 'Cofre de senhas',
            'filters' => $filters,
            'companies' => VaultCredential::companiesSummary($filters),
            'credentials' => VaultCredential::filtered($filters),
            'categories' => VaultCategory::allWithCounts(),
            'allCompanies' => Company::all(true),
        ]);
    }

    public static function show(): void
    {
        require_admin();

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
        if ($selectedParent && empty($selectedParent['is_active'])) {
            $selectedParent = null;
        }
        if ($selectedCategory && empty($selectedCategory['is_active'])) {
            $selectedCategory = null;
            $categoryId = 0;
        }
        if ($selectedCategory && !empty($selectedCategory['parent_id'])) {
            $selectedParent = VaultCategory::find((int) $selectedCategory['parent_id']);
            if ($selectedParent && empty($selectedParent['is_active'])) {
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
            'categories' => VaultCategory::allWithCounts($companyId),
            'rootCategories' => VaultCategory::withCountsByParent(null, $companyId),
            'childCategories' => $selectedParent ? VaultCategory::withCountsByParent((int) $selectedParent['id'], $companyId) : [],
            'selectedParent' => $selectedParent,
            'selectedCategory' => $selectedCategory,
            'iconOptions' => VaultCategory::iconOptions(),
        ]);
        unset($_SESSION['vault_category_errors'], $_SESSION['vault_category_old']);
    }

    public static function store(): void
    {
        require_admin();
        verify_csrf();

        $company = self::requireCompany((int) ($_POST['company_id'] ?? 0));
        [$data, $errors] = self::validatedData($company);

        if ($errors) {
            $_SESSION['vault_form_errors'] = $errors;
            $_SESSION['vault_form_old'] = self::oldData($data);
            flash('danger', 'Revise os campos da credencial.');
            redirect('/?route=vault.show&id=' . (int) $company['id'] . '&open_modal=create');
        }

        $userId = (int) current_user()['id'];
        $data['secret_value'] = CredentialCrypto::encrypt((string) $data['secret_value']);
        $data['created_by'] = $userId;
        $data['updated_by'] = $userId;
        $credentialId = VaultCredential::create($data);

        AuditLog::record([
            'action_type' => 'vault_credential_created',
            'affected_table' => 'vault_credentials',
            'affected_record_id' => $credentialId,
            'company_id' => (int) $company['id'],
            'description' => 'Credencial do cofre cadastrada.',
            'new_data' => self::auditData($data),
        ]);

        flash('success', 'Credencial cadastrada com sucesso.');
        redirect('/?route=vault.show&id=' . (int) $company['id']);
    }

    public static function update(): void
    {
        require_admin();
        verify_csrf();

        $credential = self::requireCredential((int) ($_POST['id'] ?? 0));
        $company = self::requireCompany((int) $credential['company_id']);
        [$data, $errors] = self::validatedData($company, $credential);

        if ($errors) {
            $_SESSION['vault_form_errors'] = $errors;
            $_SESSION['vault_form_old'] = array_merge(self::oldData($data), [
                'id' => (int) $credential['id'],
            ]);
            flash('danger', 'Revise os campos da credencial.');
            redirect('/?route=vault.show&id=' . (int) $company['id'] . '&open_modal=edit');
        }

        if ($data['secret_value'] === null || $data['secret_value'] === '') {
            $data['secret_value'] = $credential['secret_value'];
        } else {
            $data['secret_value'] = CredentialCrypto::encrypt((string) $data['secret_value']);
        }

        $data['updated_by'] = (int) current_user()['id'];
        unset($data['company_id'], $data['created_by']);

        $changes = self::changedFields($credential, $data);
        VaultCredential::update((int) $credential['id'], $data);

        if ($changes) {
            AuditLog::record([
                'action_type' => 'vault_credential_updated',
                'affected_table' => 'vault_credentials',
                'affected_record_id' => (int) $credential['id'],
                'company_id' => (int) $company['id'],
                'description' => 'Credencial do cofre alterada.',
                'old_data' => $changes['old'],
                'new_data' => $changes['new'],
            ]);
        }

        flash('success', 'Credencial atualizada com sucesso.');
        redirect('/?route=vault.show&id=' . (int) $company['id']);
    }

    public static function deactivate(): void
    {
        require_admin();
        verify_csrf();

        $credential = self::requireCredential((int) ($_POST['id'] ?? 0));
        VaultCredential::deactivate((int) $credential['id'], (int) current_user()['id']);

        AuditLog::record([
            'action_type' => 'vault_credential_deactivated',
            'affected_table' => 'vault_credentials',
            'affected_record_id' => (int) $credential['id'],
            'company_id' => (int) $credential['company_id'],
            'description' => 'Credencial do cofre desativada.',
            'old_data' => ['Status' => 'Ativa'],
            'new_data' => ['Status' => 'Inativa'],
        ]);

        flash('success', 'Credencial desativada.');
        redirect('/?route=vault.show&id=' . (int) $credential['company_id']);
    }

    public static function reveal(): void
    {
        require_admin();

        if (!is_post()) {
            ApiResponse::error('method_not_allowed', 'Método não permitido.', 405);
        }

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

            if (!password_verify($password, (string) $currentUser['password_hash'])) {
                AuditLog::record([
                    'action_type' => 'vault_credential_reveal_password_failed',
                    'affected_table' => 'users',
                    'affected_record_id' => (int) $currentUser['id'],
                    'description' => 'Senha incorreta ao tentar revelar credencial do cofre.',
                ]);
                ApiResponse::error('password_invalid', 'Senha inválida.', 403);
            }
        }

        $credential = self::requireCredential((int) ($_POST['id'] ?? 0));
        $value = CredentialCrypto::decrypt((string) ($credential['secret_value'] ?? ''));
        if ($value === null || $value === '' || $value === '[credencial inválida]') {
            ApiResponse::error('credential_invalid', 'Credencial indisponível ou inválida.', 422);
        }

        VaultCredential::markRevealed((int) $credential['id'], (int) current_user()['id']);
        AuditLog::record([
            'action_type' => 'vault_credential_revealed',
            'affected_table' => 'vault_credentials',
            'affected_record_id' => (int) $credential['id'],
            'company_id' => (int) $credential['company_id'],
            'description' => 'Administrador revelou credencial do cofre.',
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

        ApiResponse::ok([
            'id' => (int) $credential['id'],
            'title' => $credential['title'],
            'value' => $value,
        ]);
    }

    public static function storeCategory(): void
    {
        require_admin();
        verify_csrf();

        $company = self::requireCompany((int) ($_POST['company_id'] ?? 0));
        [$data, $errors] = self::validatedCategoryData();

        if ($errors) {
            $_SESSION['vault_category_errors'] = $errors;
            $_SESSION['vault_category_old'] = $data;
            flash('danger', 'Revise os campos da categoria.');
            $modal = !empty($data['parent_id']) ? 'subcategory' : 'category';
            redirect('/?route=vault.show&id=' . (int) $company['id'] . '&open_category_modal=' . $modal);
        }

        $userId = (int) current_user()['id'];
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
        $company = Company::find($companyId);

        if (!$company || empty($company['is_active'])) {
            http_response_code(404);
            view('errors/404', ['title' => 'Empresa não encontrada']);
            exit;
        }

        return $company;
    }

    private static function requireCredential(int $credentialId): array
    {
        $credential = VaultCredential::find($credentialId);

        if (!$credential || empty($credential['is_active'])) {
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
            'is_active' => 1,
        ];

        $errors = [];

        if ($data['title'] === '') {
            $errors['title'] = 'Campo obrigatório.';
        } elseif (strlen($data['title']) > 160) {
            $errors['title'] = 'Deve ter no máximo 160 caracteres.';
        }

        $category = $data['category_id'] !== null ? VaultCategory::find((int) $data['category_id']) : null;
        if ($data['category_id'] !== null && (!$category || empty($category['is_active']))) {
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

        return [$data, $errors];
    }

    private static function validatedCategoryData(): array
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
        if ($data['parent_id'] !== null && (!$parent || empty($parent['is_active']))) {
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
        unset($data['secret_value']);

        return $data;
    }

    private static function auditData(array $data): array
    {
        $clean = $data;
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
            'secret_value' => 'Senha',
            'notes' => 'Observações',
            'is_active' => 'Status',
        ];

        $changes = ['old' => [], 'new' => []];
        foreach ($labels as $field => $label) {
            $oldValue = (string) ($old[$field] ?? '');
            $newValue = (string) ($new[$field] ?? '');

            if ($oldValue !== $newValue) {
                $protected = $field === 'secret_value';
                $changes['old'][$label] = $protected ? '[protegido]' : $oldValue;
                $changes['new'][$label] = $protected ? '[protegido]' : $newValue;
            }
        }

        return $changes['old'] ? $changes : [];
    }
}
