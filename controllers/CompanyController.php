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

    private static function requireCompany(): array
    {
        $company = Company::find((int) ($_GET['id'] ?? 0));

        if (!$company) {
            http_response_code(404);
            view('errors/404', ['title' => 'Empresa nao encontrada']);
            exit;
        }

        return $company;
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
            $errors['name'] = 'Campo obrigatorio.';
        } elseif (Company::duplicateNameExists($data['name'], $ignoreId)) {
            $errors['name'] = 'Ja existe uma empresa com este nome.';
        }

        return [$data, $errors];
    }

    private static function changedFields(array $old, array $new): array
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
}
