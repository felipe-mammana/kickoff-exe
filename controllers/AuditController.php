<?php

declare(strict_types=1);

class AuditController
{
    public static function index(): void
    {
        require_permission('audit.view');

        $filters = [
            'user_id' => trim((string) ($_GET['user_id'] ?? '')),
            'company_id' => trim((string) ($_GET['company_id'] ?? '')),
            'module' => trim((string) ($_GET['module'] ?? '')),
            'action_type' => trim((string) ($_GET['action_type'] ?? '')),
            'criticality' => trim((string) ($_GET['criticality'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];
        $filterErrors = [];
        foreach (['date_from' => 'Data inicial', 'date_to' => 'Data final'] as $key => $label) {
            if ($filters[$key] !== '' && !ProtectedAuditFiles::isValidDate($filters[$key])) {
                $filterErrors[] = $label . ' inválida.';
            }
        }
        if (!$filterErrors && $filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
            $filterErrors[] = 'A data inicial não pode ser posterior à data final.';
        }
        if (!in_array($filters['criticality'], ['', 'critical'], true)) {
            $filterErrors[] = 'Criticidade inválida.';
        }

        $logs = $filterErrors ? [] : AuditLog::search($filters);

        view('audit/index', [
            'title' => 'Logs',
            'logs' => $logs,
            'users' => User::all(),
            'companies' => Company::all(),
            'modules' => AuditLog::modules(),
            'actionTypes' => AuditLog::actionTypes(),
            'criticalCount' => $filterErrors ? 0 : AuditLog::criticalCount($filters),
            'filters' => $filters,
            'filterError' => implode(' ', $filterErrors),
        ]);
    }
}
