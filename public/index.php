<?php

declare(strict_types=1);

$basePath = is_file(__DIR__ . '/includes/bootstrap.php') ? __DIR__ : dirname(__DIR__);

require_once $basePath . '/includes/bootstrap.php';

if (ApiRouter::isApiRequest()) {
    try {
        ApiRouter::dispatch();
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        ApiResponse::error('server_error', APP_DEBUG ? $exception->getMessage() : 'Erro interno da API.', 500);
    }
}

$route = $_GET['route'] ?? 'dashboard';

try {
    switch ($route) {
        case 'login':
            AuthController::login();
            break;
        case 'logout':
            AuthController::logout();
            break;
        case 'dashboard':
            DashboardController::index();
            break;
        case 'companies.index':
            CompanyController::index();
            break;
        case 'companies.create':
            CompanyController::create();
            break;
        case 'companies.store':
            CompanyController::store();
            break;
        case 'companies.show':
            CompanyController::show();
            break;
        case 'companies.edit':
            CompanyController::edit();
            break;
        case 'companies.update':
            CompanyController::update();
            break;
        case 'companies.deactivate':
            CompanyController::deactivate();
            break;
        case 'machines.create':
            MachineController::create();
            break;
        case 'machines.store':
            MachineController::store();
            break;
        case 'machines.show':
            MachineController::show();
            break;
        case 'machines.edit':
            MachineController::edit();
            break;
        case 'machines.update':
            MachineController::update();
            break;
        case 'machines.deletePhoto':
            MachineController::deletePhoto();
            break;
        case 'machines.deactivate':
            MachineController::deactivate();
            break;
        case 'audit.index':
            AuditController::index();
            break;
        case 'users.index':
            UserController::index();
            break;
        case 'settings.index':
            SettingsController::index();
            break;
        case 'export.download':
            ExportController::download();
            break;
        default:
            DashboardController::notFound();
            break;
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    view('errors/500');
}
