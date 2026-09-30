<?php

declare(strict_types=1);

class DashboardController
{
    public static function index(): void
    {
        require_auth();
        if (!can_permission('reports.view')) {
            foreach (['vault.view' => 'vault.index', 'companies.view' => 'companies.index', 'users.view' => 'users.index', 'sessions.view' => 'sessions.index', 'audit.view' => 'audit.index'] as $permission => $route) {
                if (can_permission($permission)) redirect('/?route=' . $route);
            }
            redirect('/?route=settings.account');
        }
        require_permission('reports.view');

        $companies = can_permission('companies.view') ? Company::all(true) : [];
        $companyId = (int) ($_GET['company_id'] ?? ($companies[0]['id'] ?? 0));
        $company = $companyId && can_permission('companies.view') ? Company::find($companyId) : null;
        if ($company && empty($company['is_active'])) {
            flash('danger', 'Empresa inativa não permite cadastro ou gestão de dispositivos pelo dashboard.');
            redirect('/');
        }

        $filters = [
            'device_type' => trim((string) ($_GET['device_type'] ?? '')),
            'tag' => trim((string) ($_GET['tag'] ?? '')),
            'employee_name' => trim((string) ($_GET['employee_name'] ?? '')),
            'department' => trim((string) ($_GET['department'] ?? '')),
            'computer_model' => trim((string) ($_GET['computer_model'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? 'active')),
            'created_at' => trim((string) ($_GET['created_at'] ?? '')),
        ];
        $machines = $company && can_permission('machines.view') ? Machine::byCompany((int) $company['id'], $filters) : [];
        $photosByMachine = MachinePhoto::groupedByMachines(array_column($machines, 'id'));
        $stats = $company && can_permission('machines.view') ? Machine::stats((int) $company['id']) : ['total' => 0, 'tflux' => 0, 'antivirus' => 0, 'requesters' => 0];

        view('companies/dashboard', [
            'title' => 'Dashboard',
            'companies' => $companies,
            'company' => $company,
            'machines' => $machines,
            'photosByMachine' => $photosByMachine,
            'stats' => $stats,
            'filters' => $filters,
            'deviceTypes' => Machine::deviceTypes(),
        ]);
    }

    public static function notFound(): void
    {
        http_response_code(404);
        view('errors/404', ['title' => 'Página não encontrada']);
    }
}
