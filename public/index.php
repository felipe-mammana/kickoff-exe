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
        case 'login.2fa.cancel':
            AuthController::cancelTwoFactor();
            break;
        case 'login.2fa.email':
            AuthController::sendTwoFactorEmailCode();
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
        case 'companies.reactivate':
            CompanyController::reactivate();
            break;
        case 'companies.destroy':
            CompanyController::destroy();
            break;
        case 'companies.attachments.store':
            CompanyController::storeAttachment();
            break;
        case 'companies.attachments.download':
            CompanyController::downloadAttachment();
            break;
        case 'companies.attachments.delete':
            CompanyController::deleteAttachment();
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
        case 'machines.photos.view':
            MachineController::viewPhoto();
            break;
        case 'machines.revealCredential':
            MachineController::revealCredential();
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
        case 'users.store':
            UserController::store();
            break;
        case 'users.update':
            UserController::update();
            break;
        case 'users.resetPassword':
            UserController::resetPassword();
            break;
        case 'users.setStatus':
            UserController::setStatus();
            break;
        case 'vault.index':
            VaultController::index();
            break;
        case 'vault.show':
            VaultController::show();
            break;
        case 'vault.store':
            VaultController::store();
            break;
        case 'vault.update':
            VaultController::update();
            break;
        case 'vault.deactivate':
            VaultController::deactivate();
            break;
        case 'vault.reveal':
            VaultController::reveal();
            break;
        case 'vault.categories.store':
            VaultController::storeCategory();
            break;
        case 'settings.index':
            SettingsController::index();
            break;
        case 'settings.profile.update':
            SettingsController::updateProfile();
            break;
        case 'settings.password.update':
            SettingsController::updatePassword();
            break;
        case 'settings.preferences.update':
            SettingsController::updatePreferences();
            break;
        case 'settings.security.update':
            SettingsController::updateSecurityPreferences();
            break;
        case 'settings.sessions.endOther':
            SettingsController::endOtherSessions();
            break;
        case 'settings.apiTokens.store':
            SettingsController::createApiToken();
            break;
        case 'settings.apiTokens.revoke':
            SettingsController::revokeApiToken();
            break;
        case 'settings.2fa.prepare':
            SettingsController::prepareTwoFactor();
            break;
        case 'settings.2fa.cancel':
            SettingsController::cancelTwoFactorSetup();
            break;
        case 'settings.2fa.email.test':
            SettingsController::sendTwoFactorTestEmail();
            break;
        case 'settings.2fa.enable':
            SettingsController::enableTwoFactor();
            break;
        case 'settings.2fa.disable':
            SettingsController::disableTwoFactor();
            break;
        case 'maintenance.exportCleanDatabase':
            MaintenanceController::exportCleanDatabase();
            break;
        case 'maintenance.exportFullBackup':
            MaintenanceController::exportFullBackup();
            break;
        case 'maintenance.importDatabase':
            MaintenanceController::importDatabase();
            break;
        case 'maintenance.cleanupOrphans':
            MaintenanceController::cleanupOrphans();
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
    view('errors/500', [
        'title' => 'Erro interno',
        'exception' => $exception,
    ]);
}
