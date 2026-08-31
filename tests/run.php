<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$localConfigFile = $basePath . '/config/local.php';
$localConfig = is_file($localConfigFile) ? require $localConfigFile : [];
$localConfig = is_array($localConfig) ? $localConfig : [];

$dbHost = getenv('DB_HOST') ?: ($localConfig['DB_HOST'] ?? '127.0.0.1');
$dbUser = getenv('DB_USER') ?: ($localConfig['DB_USER'] ?? 'root');
$dbPass = getenv('DB_PASS') ?: ($localConfig['DB_PASS'] ?? '');
$dbCharset = getenv('DB_CHARSET') ?: ($localConfig['DB_CHARSET'] ?? 'utf8mb4');
$testDb = getenv('TEST_DB_NAME') ?: ($localConfig['TEST_DB_NAME'] ?? 'inventario_ti_test');

if (!preg_match('/^[A-Za-z0-9_]+$/', $testDb)) {
    fwrite(STDERR, "Nome de banco de teste invalido: {$testDb}\n");
    exit(1);
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_KEY=' . (getenv('APP_KEY') ?: ($localConfig['APP_KEY'] ?? 'test-app-key-for-automated-checks')));
putenv('DB_HOST=' . $dbHost);
putenv('DB_USER=' . $dbUser);
putenv('DB_PASS=' . $dbPass);
putenv('DB_CHARSET=' . $dbCharset);
putenv('DB_NAME=' . $testDb);

try {
    $serverPdo = new PDO("mysql:host={$dbHost};charset={$dbCharset}", (string) $dbUser, (string) $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, "Nao foi possivel conectar ao MySQL. Ligue o MySQL no XAMPP e tente novamente.\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

$quotedDb = '`' . str_replace('`', '``', $testDb) . '`';
$serverPdo->exec("DROP DATABASE IF EXISTS {$quotedDb}");
$serverPdo->exec("CREATE DATABASE {$quotedDb} CHARACTER SET {$dbCharset} COLLATE {$dbCharset}_unicode_ci");

require_once $basePath . '/includes/bootstrap.php';

$schema = file_get_contents($basePath . '/database/schema_empty.sql');
if ($schema === false) {
    fwrite(STDERR, "Nao foi possivel ler database/schema_empty.sql.\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
    db()->exec($statement);
}

$passed = 0;
$failed = 0;
$temporaryFiles = [];

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "[OK] {$label}\n";
        return;
    }

    $failed++;
    echo "[ERRO] {$label}\n";
}

function rowCount(string $table): int
{
    return (int) db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}

try {
    $adminId = User::create([
        'name' => 'Admin Teste',
        'email' => 'admin.teste@example.com',
        'password' => 'SenhaForte123',
        'is_admin' => 1,
        'is_active' => 1,
    ]);
    $userId = User::create([
        'name' => 'Usuario Teste',
        'email' => 'usuario.teste@example.com',
        'password' => 'SenhaForte123',
        'is_admin' => 0,
        'is_active' => 1,
    ]);

    $admin = User::find($adminId);
    $standardUser = User::find($userId);
    check('Usuario admin e criado ativo', $admin !== null && (int) $admin['is_admin'] === 1 && (int) $admin['is_active'] === 1);
    check('Senha de usuario fica em hash', password_verify('SenhaForte123', (string) $admin['password_hash']));
    check('Hash nao armazena senha em texto puro', (string) $admin['password_hash'] !== 'SenhaForte123');
    check('E-mail duplicado e detectado', User::duplicateEmailExists('admin.teste@example.com') === true);

    User::setActive($userId, false);
    $inactiveSessionToken = bin2hex(random_bytes(32));
    User::setActiveSession($userId, $inactiveSessionToken);
    $_SESSION = ['user' => [
        'id' => $userId,
        'name' => $standardUser['name'],
        'email' => $standardUser['email'],
        'is_admin' => 0,
    ], 'session_token' => $inactiveSessionToken];
    check('Usuario inativo nao permanece autenticado na sessao', current_user() === null);

    $plainToken = ApiToken::generatePlainToken();
    $apiTokenId = ApiToken::create($adminId, 'Token ativo', $plainToken, date('Y-m-d H:i:s', strtotime('+1 day')));
    check('Token de usuario ativo autentica', ApiToken::findActiveByPlainToken($plainToken) !== null);
    check('Token de API pode ser revogado pelo usuario', ApiToken::revoke($apiTokenId, $adminId) === true && ApiToken::findActiveByPlainToken($plainToken) === null);
    $plainToken = ApiToken::generatePlainToken();
    ApiToken::create($adminId, 'Token ativo 2', $plainToken, date('Y-m-d H:i:s', strtotime('+1 day')));
    User::setActive($adminId, false);
    check('Token de usuario inativo e recusado', ApiToken::findActiveByPlainToken($plainToken) === null);
    User::setActive($adminId, true);

    $adminSessionToken = bin2hex(random_bytes(32));
    User::setActiveSession($adminId, $adminSessionToken);
    $_SESSION = ['user' => [
        'id' => $adminId,
        'name' => $admin['name'],
        'email' => $admin['email'],
        'is_admin' => 1,
    ], 'session_token' => $adminSessionToken];

    $twoFactorSecret = 'JBSWY3DPEHPK3PXP';
    $twoFactorCode = TwoFactorAuth::currentCode($twoFactorSecret, 1234567890);
    check('Codigo TOTP valido e aceito', TwoFactorAuth::verify($twoFactorSecret, $twoFactorCode, 0, 1234567890));
    check('Codigo por email possui 6 digitos', preg_match('/^\d{6}$/', EmailCode::generate()) === 1);
    User::updateProfile($adminId, 'Admin Atualizado', 'admin.atualizado@example.com');
    $updatedProfile = User::find($adminId);
    check('Perfil do usuario pode ser atualizado', $updatedProfile !== null && $updatedProfile['name'] === 'Admin Atualizado' && $updatedProfile['email'] === 'admin.atualizado@example.com');
    User::updateProfile($adminId, 'Admin Teste', 'admin.teste@example.com');
    User::updatePassword($adminId, 'NovaSenha123');
    $updatedPasswordUser = User::find($adminId);
    check('Usuario pode alterar propria senha', $updatedPasswordUser !== null && password_verify('NovaSenha123', (string) $updatedPasswordUser['password_hash']));
    User::updatePassword($adminId, 'SenhaForte123');
    User::updatePreferences($adminId, [
        'preferred_theme' => 'dark',
        'sidebar_default' => 'collapsed',
        'table_page_size' => 50,
        'datetime_format' => 'd/m/Y',
    ]);
    $updatedPreferencesUser = User::find($adminId);
    check('Preferencias do usuario podem ser atualizadas', $updatedPreferencesUser !== null
        && ($updatedPreferencesUser['preferred_theme'] ?? '') === 'dark'
        && ($updatedPreferencesUser['sidebar_default'] ?? '') === 'collapsed'
        && (int) ($updatedPreferencesUser['table_page_size'] ?? 0) === 50
        && ($updatedPreferencesUser['datetime_format'] ?? '') === 'd/m/Y');
    User::updatePreferences($adminId, [
        'preferred_theme' => 'light',
        'sidebar_default' => 'expanded',
        'table_page_size' => 25,
        'datetime_format' => 'd/m/Y H:i',
    ]);
    User::updateSecurityPreferences($adminId, 60, true);
    $securityPreferencesUser = User::find($adminId);
    check('Preferencias de seguranca podem ser atualizadas', $securityPreferencesUser !== null
        && (int) ($securityPreferencesUser['session_timeout_minutes'] ?? 0) === 60
        && (int) ($securityPreferencesUser['vault_require_password_reveal'] ?? 0) === 1);
    User::updateSecurityPreferences($adminId, 480, false);
    User::enableTwoFactor($adminId, $twoFactorSecret);
    $adminWithTwoFactor = User::find($adminId);
    check('2FA fica ativo no usuario', $adminWithTwoFactor !== null && (int) $adminWithTwoFactor['two_factor_enabled'] === 1);
    check('Segredo 2FA fica criptografado no banco', CredentialCrypto::isEncrypted($adminWithTwoFactor['two_factor_secret'] ?? null));
    check('Segredo 2FA pode ser recuperado', User::twoFactorSecret($adminWithTwoFactor) === $twoFactorSecret);
    User::disableTwoFactor($adminId);
    $adminWithoutTwoFactor = User::find($adminId);
    check('2FA pode ser desativado', $adminWithoutTwoFactor !== null && (int) $adminWithoutTwoFactor['two_factor_enabled'] === 0 && empty($adminWithoutTwoFactor['two_factor_secret']));

    $newSessionToken = bin2hex(random_bytes(32));
    User::setActiveSession($adminId, $newSessionToken);
    check('Nova sessao invalida a sessao anterior', current_user() === null);
    $_SESSION = ['user' => [
        'id' => $adminId,
        'name' => $admin['name'],
        'email' => $admin['email'],
        'is_admin' => 1,
    ], 'session_token' => $newSessionToken];

    $companyId = Company::create([
        'name' => 'EXE',
        'tag_pattern' => 'exe',
        'is_active' => 1,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ]);
    $company = Company::find($companyId);
    $inactiveCompanyId = Company::create([
        'name' => 'Empresa Inativa Teste',
        'tag_pattern' => 'ina',
        'is_active' => 0,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ]);
    Company::reactivate($inactiveCompanyId, $adminId);
    check('Empresa inativa pode ser reativada', !empty(Company::find($inactiveCompanyId)['is_active']));
    Company::deactivate($inactiveCompanyId, $adminId);
    $_POST = [
        'company_id' => $inactiveCompanyId,
        'device_type' => 'notebook',
        'tag_number' => '900',
        'old_hostname' => 'OLD900',
        'new_hostname' => 'NEW900',
        'employee_name' => 'Usuario Inativo',
        'department' => 'TI',
        'machine_password' => 'senha-local',
    ];
    $validator = new ReflectionMethod(MachineController::class, 'validatedData');
    $validator->setAccessible(true);
    [, $inactiveErrors] = $validator->invoke(null);
    check('Empresa inativa nao permite cadastro de dispositivo', isset($inactiveErrors['company_id']));
    $_POST = [
        'company_id' => $companyId,
        'device_type' => 'roteador',
        'tag_number' => '901',
        'admin_user' => 'admin',
        'admin_password' => 'senha-router',
        'ip_address' => '192.168.0.1',
    ];
    [, $optionalModelErrors] = $validator->invoke(null);
    check('Modelo de dispositivo nao e obrigatorio no cadastro', !isset($optionalModelErrors['computer_model']));
    $_POST = [];
    Company::delete($inactiveCompanyId);
    check('Empresa pode ser excluida totalmente', Company::find($inactiveCompanyId) === null);

    db()->prepare(
        'INSERT INTO vault_categories (parent_id, name, slug, description, icon, is_active)
         VALUES (:parent_id, :name, :slug, :description, :icon, 1)'
    )->execute([
        'parent_id' => null,
        'name' => 'Infraestrutura',
        'slug' => 'infraestrutura',
        'description' => 'Acessos tecnicos.',
        'icon' => 'router',
    ]);
    $vaultCategoryId = (int) db()->lastInsertId();
    $vaultSubcategoryId = VaultCategory::create([
        'parent_id' => $vaultCategoryId,
        'name' => 'Firewall',
        'slug' => 'firewall',
        'description' => 'Credenciais de firewall.',
        'icon' => 'shield',
        'is_active' => 1,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ]);
    $vaultSubcategory = VaultCategory::find($vaultSubcategoryId);
    check('Categoria do cofre aceita subcategoria', (int) ($vaultSubcategory['parent_id'] ?? 0) === $vaultCategoryId);
    check('Categorias principais do cofre nao misturam subcategorias', count(VaultCategory::withCountsByParent(null, $companyId)) === 1);
    check('Subcategorias do cofre abrem dentro da principal', count(VaultCategory::withCountsByParent($vaultCategoryId, $companyId)) === 1);
    check('Base de icones do cofre contem opcoes variadas', count(VaultCategory::iconOptions()) >= 24 && array_key_exists('server', VaultCategory::iconOptions()));
    $attachmentId = CompanyAttachment::create([
        'company_id' => $companyId,
        'category_id' => $vaultCategoryId,
        'disk_name' => 'teste-' . bin2hex(random_bytes(4)) . '.pdf',
        'original_name' => 'contrato.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 2048,
        'description' => 'Contrato de teste',
        'uploaded_by' => $adminId,
    ]);
    AuditLog::record([
        'action_type' => 'company_attachment_added',
        'affected_table' => 'company_attachments',
        'affected_record_id' => $attachmentId,
        'company_id' => $companyId,
        'description' => 'Anexo adicionado à empresa.',
        'new_data' => ['Arquivo' => 'contrato.pdf'],
    ]);
    check('Anexo de empresa pode ser cadastrado', CompanyAttachment::find($attachmentId) !== null);
    check('Anexo de empresa aparece na empresa correta', count(CompanyAttachment::byCompany($companyId)) === 1);
    check('Anexo de empresa aparece na categoria correta', count(CompanyAttachment::byCompanyAndCategory($companyId, $vaultCategoryId)) === 1);
    CompanyAttachment::delete($attachmentId);
    AuditLog::record([
        'action_type' => 'company_attachment_deleted',
        'affected_table' => 'company_attachments',
        'affected_record_id' => $attachmentId,
        'company_id' => $companyId,
        'description' => 'Anexo removido da empresa.',
        'old_data' => ['Arquivo' => 'contrato.pdf'],
    ]);
    check('Anexo de empresa pode ser removido', CompanyAttachment::find($attachmentId) === null);
    check('Auditoria registra exclusao de anexo', count(AuditLog::search(['company_id' => $companyId, 'action_type' => 'company_attachment_deleted'])) === 1);
    $vaultCredentialId = VaultCredential::create([
        'company_id' => $companyId,
        'category_id' => $vaultSubcategoryId,
        'title' => 'Firewall matriz',
        'service_url' => 'https://firewall.example',
        'username' => 'admin',
        'secret_value' => CredentialCrypto::encrypt('senha-cofre'),
        'notes' => 'Acesso principal',
        'is_active' => 1,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ]);
    $vaultCredential = VaultCredential::find($vaultCredentialId);
    check('Credencial do cofre fica criptografada no banco', CredentialCrypto::isEncrypted($vaultCredential['secret_value'] ?? null));
    check('Credencial do cofre aparece na busca filtrada', count(VaultCredential::filtered(['company_id' => $companyId, 'query' => 'Firewall'])) === 1);
    VaultCredential::update($vaultCredentialId, [
        'category_id' => $vaultCategoryId,
        'title' => 'Firewall matriz atualizado',
        'service_url' => 'https://firewall.example',
        'username' => 'admin',
        'secret_value' => $vaultCredential['secret_value'],
        'notes' => 'Acesso principal',
        'is_active' => 1,
        'updated_by' => $adminId,
    ]);
    $vaultCredentialUpdated = VaultCredential::find($vaultCredentialId);
    check('Edicao do cofre preserva senha criptografada quando valor nao muda', ($vaultCredentialUpdated['secret_value'] ?? '') === ($vaultCredential['secret_value'] ?? null));
    check('Credencial do cofre aparece na categoria principal', count(VaultCredential::filtered(['company_id' => $companyId, 'category_id' => $vaultCategoryId])) === 1);
    db()->prepare('UPDATE vault_categories SET is_active = 0 WHERE id = :id')->execute(['id' => $vaultSubcategoryId]);
    $_POST = [
        'company_id' => $companyId,
        'category_id' => $vaultSubcategoryId,
        'title' => 'Credencial categoria inativa',
        'secret_value' => 'segredo',
    ];
    $vaultValidator = new ReflectionMethod(VaultController::class, 'validatedData');
    $vaultValidator->setAccessible(true);
    [, $inactiveCategoryErrors] = $vaultValidator->invoke(null, $company);
    check('Cofre rejeita categoria inativa em credencial', isset($inactiveCategoryErrors['category_id']));
    db()->prepare('UPDATE vault_categories SET is_active = 1 WHERE id = :id')->execute(['id' => $vaultSubcategoryId]);
    $_POST = [];
    check('Busca do cofre por nome encontra credenciais de todas as empresas', count(VaultCredential::filtered(['search_mode' => 'credential', 'query' => 'Firewall'])) === 1);
    check('Busca do cofre por empresa encontra empresas pelo nome', count(VaultCredential::companiesSummary(['search_mode' => 'company', 'query' => 'EXE'])) === 1);
    check('Busca do cofre por empresa nao mistura nome da credencial', count(VaultCredential::companiesSummary(['search_mode' => 'company', 'query' => 'Firewall'])) === 0);
    check('Credencial do cofre pode ser revelada quando permitido', CredentialCrypto::decrypt($vaultCredentialUpdated['secret_value'] ?? null) === 'senha-cofre');
    VaultCredential::markRevealed($vaultCredentialId, $adminId);
    $vaultCredentialRevealed = VaultCredential::find($vaultCredentialId);
    check('Revelacao do cofre atualiza ultimo acesso', !empty($vaultCredentialRevealed['last_revealed_at']));
    check('Filtro do cofre trata SQL injection como texto', count(VaultCredential::filtered(['query' => "' OR 1=1 --"])) === 0);
    VaultCredential::deactivate($vaultCredentialId, $adminId);
    check('Credencial desativada sai da busca principal', count(VaultCredential::filtered(['company_id' => $companyId, 'query' => 'Firewall'])) === 0);

    check('Prefixo de notebook usa empresa', Machine::normalizeTag('42', 'notebook', $company) === 'NEXE42');
    check('Prefixo de desktop usa C + empresa', Machine::normalizeTag('7', 'cpu', $company) === 'CEXE7');
    check('Prefixo de roteador usa R + empresa', Machine::normalizeTag('8', 'roteador', $company) === 'REXE8');
    check('Prefixo de impressora usa I + empresa', Machine::normalizeTag('9', 'impressora', $company) === 'IEXE9');
    check('Prefixo de modem usa LINK', Machine::normalizeTag('123', 'modem', $company) === 'LINK123');

    $machineId = Machine::create([
        'company_id' => $companyId,
        'device_type' => 'notebook',
        'equipment_name' => null,
        'tag' => 'NEXE42',
        'old_hostname' => 'OLD-01',
        'new_hostname' => 'NEW-01',
        'employee_name' => 'Pessoa Teste',
        'department' => 'TI',
        'brand' => 'Lenovo',
        'computer_model' => 'ThinkPad',
        'operating_system' => 'Windows',
        'machine_password' => 'senha-equipamento',
        'admin_user' => 'admin',
        'admin_password' => 'senha-admin',
        'install_location' => null,
        'modem_name' => null,
        'ip_address' => null,
        'gateway' => null,
        'carrier' => null,
        'printer_brand' => null,
        'printer_connection_type' => null,
        'printer_shared' => 0,
        'notes' => 'Criado pelo teste',
        'tflux_installed' => 1,
        'antivirus_installed' => 1,
        'requester_in_tflux' => 1,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ]);

    $machineRaw = Machine::find($machineId);
    $machineDecrypted = Machine::find($machineId, true);
    check('Senha de equipamento fica criptografada no banco', CredentialCrypto::isEncrypted($machineRaw['machine_password'] ?? null));
    check('Senha de equipamento pode ser descriptografada quando permitido', ($machineDecrypted['machine_password'] ?? null) === 'senha-equipamento');
    check('Busca de duplicidade de etiqueta funciona', Machine::duplicateExists($companyId, 'tag', 'NEXE42') === true);

    $photoFileName = 'teste-docx-' . bin2hex(random_bytes(6)) . '.png';
    $photoPath = UPLOAD_PATH . '/' . $photoFileName;
    file_put_contents($photoPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=', true));
    $temporaryFiles[] = $photoPath;

    $photoId = MachinePhoto::create([
        'machine_id' => $machineId,
        'photo_type' => 'general',
        'photo_topic' => 'valor-invalido',
        'location_name' => '  ',
        'file_name' => $photoFileName,
        'original_name' => 'foto original.png',
        'mime_type' => 'image/png',
        'file_size' => 100,
    ]);
    $photo = MachinePhoto::find($photoId);
    check('Topico invalido de foto cai no padrao equipamento', ($photo['photo_topic'] ?? null) === 'equipamento');
    check('Nome de local vazio da foto fica nulo', array_key_exists('location_name', $photo) && $photo['location_name'] === null);
    check('Foto pode ser localizada pelo nome interno', MachinePhoto::findByFileName($photoFileName) !== null);
    check('URL de foto passa por rota autenticada', strpos(upload_file_url($photoFileName), '/?route=machines.photos.view&file=') === 0);
    check('URL de foto nao expoe pasta publica de uploads', strpos(upload_file_url($photoFileName), '/uploads/') !== 0);
    check('Filtro de dispositivos trata SQL injection como texto', count(Machine::byCompany($companyId, ['tag' => "' OR 1=1 --"])) === 0);

    check('Validador da API rejeita booleano invalido', isset(ApiValidator::validate(['is_active' => 'talvez'], ['is_active' => ['bool']])['is_active']));
    check('Validador da API rejeita texto acima do limite', isset(ApiValidator::validate(['name' => str_repeat('a', 161)], ['name' => ['string', 'max' => 160]])['name']));
    check('Redirect externo e normalizado para raiz', safe_redirect_path('https://evil.example') === '/');
    check('Path traversal em upload e recusado', upload_file_path('../config.php') === null);
    $fakeImagePath = STORAGE_PATH . '/fake-image-' . bin2hex(random_bytes(4)) . '.jpg';
    file_put_contents($fakeImagePath, '<?php echo "not-image";');
    $temporaryFiles[] = $fakeImagePath;
    $attachmentMimeValidator = new ReflectionMethod(CompanyController::class, 'isAttachmentMimeAllowed');
    $attachmentMimeValidator->setAccessible(true);
    check('Anexo de empresa rejeita imagem com conteudo invalido', $attachmentMimeValidator->invoke(null, 'jpg', 'image/jpeg', $fakeImagePath) === false);

    $docx = (new CompanyEquipmentDocxExporter())->build(
        $company,
        [Machine::find($machineId) ?: []],
        [$machineId => [MachinePhoto::find($photoId) ?: []]],
        ['company_id' => $companyId, 'status' => 'active']
    );
    check('Relatorio DOCX gera pacote valido', substr($docx, 0, 4) === "PK\x03\x04" && strpos($docx, 'word/document.xml') !== false);
    check('Relatorio DOCX contem resumo e filtros', strpos($docx, 'Resumo') !== false && strpos($docx, 'Filtros aplicados') !== false);
    check('Relatorio DOCX resume dispositivos por tipo', strpos($docx, 'Notebook') !== false && strpos($docx, 'CPU / Desktop') !== false);
    check('Relatorio DOCX nao mostra categorias nem fotos no resumo', strpos($docx, 'Resumo por categoria') === false && strpos($docx, 'Fotos anexadas') === false);
    check('Relatorio DOCX nao exporta marca nem modelo', strpos($docx, 'Marca') === false && strpos($docx, 'Modelo') === false && strpos($docx, 'ThinkPad') === false);
    check('Relatorio DOCX nao exporta senhas', strpos($docx, 'senha-equipamento') === false && strpos($docx, 'senha-admin') === false);

    $_SERVER['HTTP_USER_AGENT'] = 'Codex Test Browser/1.0';
    AuditLog::record([
        'action_type' => 'automated_test',
        'description' => 'Evento de teste automatizado.',
        'company_id' => $companyId,
        'machine_id' => $machineId,
    ]);
    $automatedLogs = AuditLog::search(['action_type' => 'automated_test']);
    check('Auditoria registra evento com usuario da sessao', count($automatedLogs) === 1);
    check('Auditoria registra user-agent da origem', ($automatedLogs[0]['user_agent'] ?? '') === 'Codex Test Browser/1.0');
} catch (Throwable $exception) {
    $failed++;
    echo "[ERRO] Excecao inesperada: " . $exception->getMessage() . "\n";
}

foreach ($temporaryFiles as $temporaryFile) {
    if (is_file($temporaryFile)) {
        unlink($temporaryFile);
    }
}

try {
    $serverPdo->exec("DROP DATABASE IF EXISTS {$quotedDb}");
} catch (Throwable $exception) {
    echo "[AVISO] Nao foi possivel remover o banco de teste {$testDb}: {$exception->getMessage()}\n";
}

echo "\nResultado: {$passed} OK, {$failed} erro(s).\n";
exit($failed > 0 ? 1 : 0);
