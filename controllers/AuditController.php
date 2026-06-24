<?php

declare(strict_types=1);

class AuditController
{
    public static function index(): void
    {
        require_admin();

        $filters = [
            'user_id' => trim((string) ($_GET['user_id'] ?? '')),
            'company_id' => trim((string) ($_GET['company_id'] ?? '')),
            'action_type' => trim((string) ($_GET['action_type'] ?? '')),
            'date_from' => trim((string) ($_GET['date_from'] ?? '')),
            'date_to' => trim((string) ($_GET['date_to'] ?? '')),
        ];

        view('audit/index', [
            'title' => 'Logs',
            'logs' => AuditLog::search($filters),
            'users' => User::all(),
            'companies' => Company::all(),
            'actionTypes' => AuditLog::actionTypes(),
            'filters' => $filters,
        ]);
    }
}
