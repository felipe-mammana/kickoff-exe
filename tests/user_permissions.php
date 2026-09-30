<?php
declare(strict_types=1);
// Runs inside the isolated HTTP/SMTP fixture from account_security.php.
if (PHP_SAPI !== 'cli' || APP_ENV !== 'testing') exit(1);

$permissionUserId = User::create(['name' => 'Permissions Test', 'email' => 'permissions@example.test', 'password' => 'Permissions-Test-123', 'role' => 'admin', 'is_active' => 1]);
$permissionUser = User::find($permissionUserId);
$legacy = UserPermission::effective($permissionUser);
check('Permissoes: perfil legado preserva administracao sem acesso ao cofre', in_array('users.permissions', $legacy, true) && !in_array('vault.view', $legacy, true));
UserPermission::save($permissionUserId, ['users.view'], []);
$clients[] = $permissionClient = new AccountSecurityHttpClient($baseUrl);
$permissionClient->login('permissions@example.test', 'Permissions-Test-123');
check('Permissoes: admin restrito consulta usuarios', $permissionClient->request('users.index')['status'] === 200);
foreach (['companies.index', 'machines.show&id=1', 'vault.index', 'audit.index', 'sessions.index', 'settings.devices', 'settings.microsoft.index', 'maintenance.exportFullBackup', 'export.download&type=devices&format=json'] as $route) {
    check('Permissoes: URL recusada ' . $route, $permissionClient->request($route)['status'] === 403);
}
check('Permissoes: configuracoes pessoais continuam disponiveis', $permissionClient->request('settings.account')['status'] === 200);
check('Permissoes: autoatribuicao recusada', $permissionClient->request('users.update', ['id' => $permissionUserId, 'permissions_present' => 1, 'permissions' => UserPermission::keys()])['status'] === 403);
check('Permissoes: POST sem acesso nao cria conta', $permissionClient->request('users.store', ['email' => 'forbidden@example.test'])['status'] === 403);

$token = ApiToken::generatePlainToken();
ApiToken::create($permissionUserId, 'Permissions test', $token);
$apiCheck = static function (string $path) use ($baseUrl, $token): int {
    $curl = curl_init($baseUrl . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token], CURLOPT_TIMEOUT => 10]);
    curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return $status;
};
check('Permissoes: token de admin nao ignora restricao', $apiCheck('/api/v1/companies') === 403);
UserPermission::save($permissionUserId, ['companies.view'], []);
check('Permissoes: token reflete concessao sem novo login', $apiCheck('/api/v1/companies') === 200);
UserPermission::save($permissionUserId, [], []);
check('Permissoes: token reflete revogacao imediatamente', $apiCheck('/api/v1/companies') === 403);

$grants = ['users.view', 'users.edit', 'users.permissions'];
$update = ['id' => $permissionUserId, 'role' => 'viewer', 'permissions_present' => 1, 'permissions' => $grants];
$response = $adminClient->request('users.update', $update);
check('Permissoes: alteracao aguarda segundo fator', str_contains($response['headers'], 'account.challenge') && UserPermission::effective(User::find($permissionUserId)) === []);
approveAccountChange($adminClient, $mailFile, $securityAdminId);
check('Permissoes: segundo fator aplica perfil personalizado', !UserPermission::different(UserPermission::effective(User::find($permissionUserId)), $grants) && User::roleFromUser(User::find($permissionUserId)) === 'viewer');
check('Permissoes: viewer autorizado acessa usuarios', $permissionClient->request('users.index')['status'] === 200);
check('Permissoes: nao pode assumir conta superior', $permissionClient->request('users.resetPassword', ['id' => $securityAdminId, 'password' => 'Hijack-Test-123', 'password_confirmation' => 'Hijack-Test-123'])['status'] === 403);
$permissionClient->request('users.update', ['id' => $permissionUserId, 'role' => 'admin', 'permissions_present' => 1, 'permissions' => UserPermission::keys()]);
check('Permissoes: gestor limitado nao eleva o proprio perfil', User::roleFromUser(User::find($permissionUserId)) === 'viewer');
$entry = db()->query("SELECT old_data, new_data FROM audit_logs WHERE action_type = 'user_permissions_updated' ORDER BY id DESC LIMIT 1")->fetch();
check('Permissoes: auditoria registra antes e depois', $entry && str_contains($entry['new_data'], 'users.permissions') && !str_contains($entry['old_data'], 'users.permissions'));

$creation = ['name' => 'Created Permissions', 'email' => 'created-permissions@example.test', 'role' => 'viewer', 'password' => 'Created-Permissions-123', 'password_confirmation' => 'Created-Permissions-123', 'permissions_present' => 1, 'permissions' => ['companies.view']];
$adminClient->request('users.store', $creation);
check('Permissoes: criacao nao acontece antes da confirmacao', !User::duplicateEmailExists($creation['email']));
approveAccountChange($adminClient, $mailFile, $securityAdminId);
$createdPermissionUser = db()->query("SELECT * FROM users WHERE email = 'created-permissions@example.test'")->fetch();
check('Permissoes: criacao salva apenas selecao explicita', $createdPermissionUser && UserPermission::effective($createdPermissionUser) === ['companies.view']);
if ($createdPermissionUser) {
    $permissionClient->request('users.update', ['id' => $createdPermissionUser['id'], 'role' => 'admin', 'permissions_present' => 1, 'permissions' => UserPermission::keys()]);
    check('Permissoes: gestor nao concede acima do limite', UserPermission::effective($createdPermissionUser) === ['companies.view']);
}
$snapshot = UserPermission::effective(User::find($securityUserId));
UserPermission::migrateLegacy();
check('Permissoes: migracao preserva usuario do cofre', !UserPermission::different($snapshot, UserPermission::effective(User::find($securityUserId))));
check('Permissoes: migracao e idempotente', UserPermission::migrateLegacy() === 0);

if (getenv('PERMISSIONS_UI_TEST') === '1') {
    User::create(['name' => 'Permissions UI', 'email' => 'permissions-ui@example.test', 'password' => 'Permissions-UI-123', 'role' => 'admin', 'is_active' => 1]);
    $ui = proc_open(['node', __DIR__ . '/user_permissions_ui.cjs', $baseUrl], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $uiPipes, BASE_PATH);
    fclose($uiPipes[0]);
    echo stream_get_contents($uiPipes[1]);
    echo stream_get_contents($uiPipes[2]);
    fclose($uiPipes[1]);
    fclose($uiPipes[2]);
    check('Permissoes: interface desktop e celular', proc_close($ui) === 0);
}
