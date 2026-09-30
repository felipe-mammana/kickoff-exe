<?php
$settingsTopic = $settingsTopic ?? null;
$twoFactorEnabled = !empty($accountUser['two_factor_enabled']);
$twoFactorAuthenticatorEnabled = !empty($twoFactorAuthenticatorEnabled);
$twoFactorEmailSetupPending = !empty($twoFactorEmailSetupPending);
$sessionStartedAt = $accountUser['active_session_started_at'] ?? null;
$preferredTheme = $accountUser['preferred_theme'] ?? 'light';
$sidebarDefault = $accountUser['sidebar_default'] ?? 'expanded';
$tablePageSize = (int) ($accountUser['table_page_size'] ?? 25);
$datetimeFormat = $accountUser['datetime_format'] ?? 'd/m/Y H:i';
$sessionTimeoutMinutes = (int) ($accountUser['session_timeout_minutes'] ?? 480);
$vaultRequirePasswordReveal = !empty($accountUser['vault_require_password_reveal']);
$activeSessions = $activeSessions ?? [];
$recentAccesses = $recentAccesses ?? [];
$maintenanceStatus = $maintenanceStatus ?? null;
$auditRetentionDays = (int) ($auditRetentionDays ?? 365);
$deviceSettings = is_array($deviceSettings ?? null) ? $deviceSettings : AppSetting::deviceSettings();
$vaultSettings = is_array($vaultSettings ?? null) ? $vaultSettings : AppSetting::vaultSettings();
$deviceTypes = is_array($deviceTypes ?? null) ? $deviceTypes : Machine::deviceTypes();
$deviceFieldLabels = is_array($deviceFieldLabels ?? null) ? $deviceFieldLabels : AppSetting::deviceFieldLabels();
$photoMimeLabels = is_array($photoMimeLabels ?? null) ? $photoMimeLabels : ['image/jpeg' => 'JPG / JPEG', 'image/png' => 'PNG', 'image/webp' => 'WEBP'];
$attachmentExtensionLabels = array_combine(AppSetting::defaultAttachmentExtensions(), array_map('strtoupper', AppSetting::defaultAttachmentExtensions()));
$vaultIconOptions = VaultCategory::iconOptions();
$vaultCustomFieldTypeLabels = is_array($vaultCustomFieldTypeLabels ?? null) ? $vaultCustomFieldTypeLabels : AppSetting::vaultCustomFieldTypeLabels();
$accountRole = User::roleFromUser($accountUser);
$accountRoleIcons = ['admin' => 'settings', 'editor' => 'edit-3', 'viewer' => 'users'];
$accountRoleClasses = ['admin' => 'info', 'editor' => 'success', 'viewer' => 'neutral'];
$editableLabelPrefix = static function (string $template): string {
    return trim(str_replace(['{empresa}', '{EMPRESA}'], '', $template));
};
$usesCompanyInitials = static function (string $template): bool {
    return stripos($template, '{empresa}') !== false;
};
$nameParts = preg_split('/\s+/', trim((string) $accountUser['name'])) ?: [];
$initials = strtoupper(substr($nameParts[0] ?? 'E', 0, 1) . substr($nameParts[1] ?? 'X', 0, 1));

$renderVaultCustomFields = static function (string $baseName, array $fields) use ($vaultCustomFieldTypeLabels): void {
    ?>
    <details class="vault-default-fields" data-vault-custom-field-list>
        <summary class="vault-default-fields-head">
            <div>
                <strong>Campos personalizados</strong>
                <span>Campos que aparecem no cadastro da credencial.</span>
            </div>
            <span class="vault-collapse-chevron" aria-hidden="true"><?= icon('chevron-down') ?></span>
        </summary>
        <div class="vault-default-fields-content">
            <button class="btn btn-muted compact-btn" type="button" data-vault-add-custom-field><?= icon('plus') ?><span>Adicionar campo</span></button>
            <div class="vault-default-field-list">
            <?php foreach ($fields as $fieldIndex => $field): ?>
                <div class="vault-default-field-row" data-vault-custom-field>
                    <input type="hidden" name="<?= e($baseName) ?>[fields][<?= (int) $fieldIndex ?>][key]" value="<?= e((string) ($field['key'] ?? '')) ?>">
                    <label class="field">
                        <span>Nome do campo</span>
                        <input type="text" name="<?= e($baseName) ?>[fields][<?= (int) $fieldIndex ?>][label]" value="<?= e((string) ($field['label'] ?? '')) ?>" maxlength="80">
                    </label>
                    <label class="field">
                        <span>Tipo</span>
                        <select name="<?= e($baseName) ?>[fields][<?= (int) $fieldIndex ?>][type]">
                            <?php foreach ($vaultCustomFieldTypeLabels as $type => $label): ?>
                                <option value="<?= e($type) ?>" <?= (string) ($field['type'] ?? 'text') === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="toggle-field vault-default-field-required">
                        <input type="checkbox" name="<?= e($baseName) ?>[fields][<?= (int) $fieldIndex ?>][required]" value="1" <?= !empty($field['required']) ? 'checked' : '' ?>>
                        <span>Obrigatório</span>
                    </label>
                    <button class="icon-btn danger" type="button" data-vault-remove-custom-field aria-label="Remover campo" title="Remover campo"><?= icon('trash-2') ?></button>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php
};

$topics = [
    'account' => ['route' => 'settings.account', 'icon' => 'user', 'eyebrow' => 'Segurança & perfil', 'title' => 'Configurações da conta', 'card_title' => 'Conta', 'description' => 'Dados do perfil e troca da senha de acesso.'],
    'preferences' => ['route' => 'settings.preferences', 'icon' => 'settings', 'eyebrow' => 'Preferências', 'title' => 'Preferências do sistema', 'card_title' => 'Preferências do sistema', 'description' => 'Tema, menu lateral, tabelas e formato de data.'],
    'two_factor' => ['route' => 'settings.twoFactor', 'icon' => 'shield', 'eyebrow' => 'Acesso seguro', 'title' => 'Autenticação em dois fatores', 'card_title' => 'Autenticação em dois fatores', 'description' => 'Senha, aplicativo autenticador e código por e-mail no login.', 'status' => $twoFactorEnabled ? 'Ativo' : 'Inativo', 'status_class' => $twoFactorEnabled ? 'success' : 'neutral'],
    'session_limit' => ['route' => 'settings.sessionLimit', 'icon' => 'history', 'eyebrow' => 'Sessões', 'title' => 'Limite de sessão', 'card_title' => 'Limite de sessão', 'description' => 'Apenas uma sessão ativa por usuário.', 'status' => 'Ativo', 'status_class' => 'success'],
    'security' => ['route' => 'settings.security', 'icon' => 'file-clock', 'eyebrow' => 'Segurança', 'title' => 'Segurança da conta', 'card_title' => 'Segurança', 'description' => 'Sessões, acessos, expiração e proteção do cofre.'],
];

if (can_permission('settings.manage')) {
    $topics['audit'] = ['route' => 'settings.audit', 'icon' => 'file-clock', 'eyebrow' => 'Auditoria', 'title' => 'Auditoria do sistema', 'card_title' => 'Auditoria', 'description' => 'Retenção de logs, exportação por período e eventos críticos.'];
    $topics['devices'] = ['route' => 'settings.devices', 'icon' => 'monitor-cog', 'eyebrow' => 'Empresas e dispositivos', 'title' => 'Empresas e dispositivos', 'card_title' => 'Empresas e dispositivos', 'description' => 'Prefixos, categorias, obrigatoriedade e uploads.'];
    $topics['vault'] = ['route' => 'settings.vault', 'icon' => 'lock', 'eyebrow' => 'Cofre de senhas', 'title' => 'Cofre de senhas', 'card_title' => 'Cofre de senhas', 'description' => 'Categorias, cópia, revelação, logs e validade das credenciais.'];
    if (is_array($maintenanceStatus)) {
        $topics['maintenance'] = ['route' => 'settings.maintenance', 'icon' => 'database', 'eyebrow' => 'Manutenção', 'title' => 'Backup e manutenção', 'card_title' => 'Backup e manutenção', 'description' => 'Exporte backups, importe SQL e remova arquivos órfãos.'];
        $topics['auditFiles'] = ['route' => 'settings.auditFiles', 'icon' => 'lock', 'eyebrow' => 'Auditoria', 'title' => 'Arquivos de auditoria', 'card_title' => 'Arquivos de auditoria', 'description' => 'Registros protegidos com confirmacao de senha.'];
    }
}

unset($topics['vault']);
if (can_permission('vault.configure')) {
    $topics['vault'] = ['route' => 'settings.vault', 'icon' => 'lock', 'eyebrow' => 'Cofre', 'title' => 'Cofre de senhas', 'card_title' => 'Cofre de senhas', 'description' => 'Configuracoes do cofre.'];
}
if (can_permission('settings.backup') || can_permission('settings.restore')) $topics['maintenance'] = ['route' => 'settings.maintenance', 'icon' => 'database', 'eyebrow' => 'Manutencao', 'title' => 'Backup e manutencao', 'card_title' => 'Backup e manutencao', 'description' => ''];
if (can_permission('audit.view')) $topics['auditFiles'] = ['route' => 'settings.auditFiles', 'icon' => 'lock', 'eyebrow' => 'Auditoria', 'title' => 'Arquivos de auditoria', 'card_title' => 'Arquivos de auditoria', 'description' => ''];
$activeTopic = is_string($settingsTopic) && isset($topics[$settingsTopic]) ? $topics[$settingsTopic] : null;
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <?php if ($activeTopic): ?>
        <a href="/?route=settings.index">Configurações</a>
        <span><?= icon('chevron-right') ?></span>
        <strong><?= e($activeTopic['card_title']) ?></strong>
    <?php else: ?>
        <strong>Configurações</strong>
    <?php endif; ?>
</nav>

<?php if (!$activeTopic): ?>
    <section class="asset-page-head settings-overview-head">
        <div>
            <span class="eyebrow">Conta e segurança</span>
            <h1>Configurações</h1>
            <p>Gerencie a segurança da sua conta e preferências operacionais.</p>
        </div>
        <div class="header-actions">
            <?php if (can_permission('settings.manage')): ?>
                <a class="btn btn-muted" href="/?route=settings.microsoft.index"><?= icon('mail') ?><span>Email Microsoft</span></a>
                <a class="btn btn-muted" href="/?route=audit.index"><?= icon('file-clock') ?><span>Auditoria</span></a>
            <?php endif; ?>
            <a class="btn btn-primary" href="/"><?= icon('layout-dashboard') ?><span>Dashboard</span></a>
        </div>
    </section>

    <section class="settings-hero-grid">
        <article class="settings-hero-card">
            <span class="settings-avatar"><?= e($initials) ?></span>
            <div>
                <strong><?= e($accountUser['name']) ?></strong>
                <span><?= e($accountUser['email']) ?></span>
            </div>
        </article>
        <article class="settings-hero-card">
            <span class="summary-icon"><?= icon('shield') ?></span>
            <div>
                <span>2FA da conta</span>
                <strong><?= $twoFactorEnabled ? 'Ativo' : 'Inativo' ?></strong>
            </div>
            <i class="settings-status-dot <?= $twoFactorEnabled ? 'success' : 'warning' ?>" aria-hidden="true"></i>
        </article>
        <article class="settings-hero-card">
            <span class="summary-icon"><?= icon('history') ?></span>
            <div>
                <span>Limite simultâneo</span>
                <strong>1 sessão</strong>
            </div>
            <span class="status-chip success">Ativo</span>
        </article>
    </section>

    <section class="settings-card-list">
        <?php foreach ($topics as $key => $topic): ?>
            <article class="settings-topic-card <?= in_array($key, ['account', 'preferences', 'two_factor'], true) ? 'wide' : '' ?>">
                <div class="settings-topic-main">
                    <span class="settings-topic-icon"><?= icon($topic['icon']) ?></span>
                    <div>
                        <h2><?= e($topic['card_title']) ?></h2>
                        <p><?= e($topic['description']) ?></p>
                    </div>
                </div>
                <div class="settings-head-actions">
                    <?php if (!empty($topic['status'])): ?>
                        <span class="status-chip <?= e((string) ($topic['status_class'] ?? 'neutral')) ?>"><?= e((string) $topic['status']) ?></span>
                    <?php endif; ?>
                    <?php if ($key === 'security' && can_permission('settings.manage')): ?>
                        <a class="link-primary" href="/?route=audit.index">Ver logs</a>
                    <?php endif; ?>
                    <a class="btn btn-muted" href="/?route=<?= e($topic['route']) ?>"><?= icon('settings') ?><span>Abrir configurações</span></a>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php else: ?>
    <section class="settings-detail-shell">
        <header class="settings-detail-head">
            <div class="settings-detail-title">
                <span class="settings-topic-icon"><?= icon($activeTopic['icon']) ?></span>
                <div>
                    <span class="eyebrow"><?= e($activeTopic['eyebrow']) ?></span>
                    <h1><?= e($activeTopic['title']) ?></h1>
                </div>
            </div>
            <a class="btn btn-muted" href="/?route=settings.index"><?= icon('x') ?><span>Fechar configurações</span></a>
        </header>

        <?php if ($settingsTopic === 'account'): ?>
            <?php if (!empty($pendingEmailChange)): ?>
                <section class="account-email-verification" aria-labelledby="email-change-title">
                    <div class="settings-form-head">
                        <h3 id="email-change-title">Confirmar novo e-mail</h3>
                        <p>Código enviado para <strong><?= e($pendingEmailChange['email']) ?></strong>. Seu e-mail atual continua ativo até a confirmação.</p>
                    </div>
                    <form action="/?route=settings.email.confirm" method="post" class="account-email-verification-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="field"><span>Código de verificação</span><input name="email_change_code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                        <div class="form-actions">
                            <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Confirmar e-mail</span></button>
                            <button class="btn btn-muted" type="submit" formaction="/?route=settings.email.resend" formnovalidate data-email-cooldown="<?= (int) ($emailRetryAfter ?? 0) ?>"><?= icon('mail') ?><span>Reenviar código</span></button>
                            <button class="btn btn-muted" type="submit" formaction="/?route=settings.email.cancel" formnovalidate>Cancelar alteração</button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>
            <div class="settings-account-grid">
                <form class="company-form settings-security-form settings-detail-card" action="/?route=settings.profile.update" method="post" novalidate data-account-confirmation-form data-original-email="<?= e($accountUser['email']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="settings-form-head"><h3>Perfil da conta</h3><p>Atualize nome e e-mail do usuário conectado.</p></div>
                    <div class="account-role-card">
                        <span class="role-option-icon"><?= icon($accountRoleIcons[$accountRole] ?? 'users') ?></span>
                        <div>
                            <small>Nível de acesso</small>
                            <strong><?= e(User::roleLabel($accountRole)) ?></strong>
                        </div>
                        <span class="status-chip <?= e($accountRoleClasses[$accountRole] ?? 'neutral') ?>">Atual</span>
                    </div>
                    <label class="field"><span>Nome</span><input type="text" name="name" value="<?= e($accountUser['name']) ?>" maxlength="120" required></label>
                    <label class="field"><span>E-mail</span><input type="email" name="email" value="<?= e($accountUser['email']) ?>" maxlength="160" required></label>
                    <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar perfil</span></button>
                    <?php
                    $confirmationId = 'account-email';
                    $confirmationTitle = 'Confirmar troca de e-mail';
                    $confirmationDescription = 'Confirme sua senha para enviar um código ao novo endereço de e-mail.';
                    require BASE_PATH . '/views/partials/account-password-modal.php';
                    ?>
                </form>
                <form class="company-form settings-security-form settings-detail-card" action="/?route=settings.password.update" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="settings-form-head"><h3>Alterar senha</h3><p>Troque a senha usada para entrar no sistema.</p></div>
                    <label class="field"><span>Senha atual</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                    <label class="field"><span>Nova senha</span><input type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" pattern="(?=.*\p{Ll})(?=.*\p{Lu})(?=.*[0-9])(?=.*[^\p{L}\p{N}\s]).{8,}" title="Minimo 8 caracteres: maiuscula, minuscula, numero e especial." required><?php require BASE_PATH . '/views/partials/password-requirements.php'; ?></label>
                    <label class="field"><span>Confirmar nova senha</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                    <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Alterar senha</span></button>
                </form>
            </div>
        <?php elseif ($settingsTopic === 'preferences'): ?>
            <form class="company-form settings-security-form settings-preferences-form settings-detail-card" action="/?route=settings.preferences.update" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Tema padrão</span><select name="preferred_theme" required><option value="light" <?= $preferredTheme === 'light' ? 'selected' : '' ?>>Claro</option><option value="dark" <?= $preferredTheme === 'dark' ? 'selected' : '' ?>>Escuro</option></select></label>
                <label class="field"><span>Menu no computador</span><select name="sidebar_default" required><option value="expanded" <?= $sidebarDefault === 'expanded' ? 'selected' : '' ?>>Aberto</option><option value="collapsed" <?= $sidebarDefault === 'collapsed' ? 'selected' : '' ?>>Recolhido</option></select></label>
                <label class="field"><span>Itens por tabela</span><select name="table_page_size" required><?php foreach ([10, 25, 50, 100] as $size): ?><option value="<?= $size ?>" <?= $tablePageSize === $size ? 'selected' : '' ?>><?= $size ?> itens</option><?php endforeach; ?></select></label>
                <label class="field"><span>Formato de data</span><select name="datetime_format" required><option value="d/m/Y H:i" <?= $datetimeFormat === 'd/m/Y H:i' ? 'selected' : '' ?>>31/08/2026 14:30</option><option value="d/m/Y" <?= $datetimeFormat === 'd/m/Y' ? 'selected' : '' ?>>31/08/2026</option><option value="Y-m-d H:i" <?= $datetimeFormat === 'Y-m-d H:i' ? 'selected' : '' ?>>2026-08-31 14:30</option><option value="Y-m-d" <?= $datetimeFormat === 'Y-m-d' ? 'selected' : '' ?>>2026-08-31</option></select></label>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar preferências</span></button>
            </form>
        <?php elseif ($settingsTopic === 'two_factor'): ?>
            <?php if ($twoFactorEnabled): ?>
                <div class="settings-security-panel settings-detail-card">
                    <div class="settings-readonly"><p>O 2FA está ativo por <?= $twoFactorAuthenticatorEnabled ? 'aplicativo autenticador e código por e-mail' : 'código por e-mail' ?>.</p></div>
                    <form action="/?route=settings.2fa.email.test" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-muted" type="submit"><?= icon('mail') ?><span>Testar código por e-mail</span></button></form>
                    <form class="company-form settings-security-form" action="/?route=settings.2fa.disable" method="post" novalidate data-two-factor-disable-form>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-danger" type="button" data-two-factor-disable-open><?= icon('trash-2') ?><span>Desativar 2FA</span></button>

                        <div class="company-modal two-factor-disable-modal" data-two-factor-disable-modal hidden>
                            <div class="company-modal-dialog confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="two-factor-disable-title">
                                <header class="modal-head">
                                    <div>
                                        <span class="eyebrow">Confirmação</span>
                                        <h2 id="two-factor-disable-title">Desativar 2FA</h2>
                                    </div>
                                    <button class="icon-btn" type="button" data-two-factor-disable-close aria-label="Fechar"><?= icon('x') ?></button>
                                </header>
                                <div class="confirm-modal-body two-factor-disable-body">
                                    <p>Informe sua senha atual para confirmar a desativação da autenticação em dois fatores.</p>
                                    <label class="field">
                                        <span>Senha atual</span>
                                        <input type="password" name="password" autocomplete="current-password" required disabled data-two-factor-disable-password>
                                    </label>
                                </div>
                                <div class="form-actions confirm-modal-actions">
                                    <button class="btn btn-muted" type="button" data-two-factor-disable-close>Cancelar</button>
                                    <button class="btn btn-danger" type="submit"><?= icon('trash-2') ?><span>Desativar</span></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="<?= ($twoFactorEmailSetupPending || $twoFactorSetupSecret) ? 'settings-two-factor-step' : 'settings-two-column' ?>">
                    <?php if (!$twoFactorEmailSetupPending): ?>
                    <section class="settings-security-panel settings-detail-card">
                        <div class="settings-form-head"><h3>Aplicativo autenticador</h3><p>Use Google Authenticator, Microsoft Authenticator ou aplicativo compatível.</p></div>
                        <?php if (!$twoFactorSetupSecret): ?>
                            <form action="/?route=settings.2fa.prepare" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Configurar aplicativo</span></button></form>
                        <?php else: ?>
                            <div class="two-factor-setup">
                                <div><span class="eyebrow">Chave manual</span><code><?= e($twoFactorSetupSecret) ?></code><small>Adicione esta chave no aplicativo autenticador e informe o código gerado.</small></div>
                                <?php if ($twoFactorProvisioningUri): ?><div><span class="eyebrow">URI de configuração</span><code><?= e($twoFactorProvisioningUri) ?></code><small>Use apenas se o aplicativo permitir colar uma URI otpauth.</small></div><?php endif; ?>
                            </div>
                            <form class="company-form settings-security-form" action="/?route=settings.2fa.enable" method="post" novalidate>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <label class="field"><span>Senha atual</span><input type="password" name="password" autocomplete="current-password" required></label>
                                <label class="field"><span>Código do autenticador</span><input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                                <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Ativar aplicativo</span></button>
                            </form>
                            <form action="/?route=settings.2fa.cancel" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-muted" type="submit"><?= icon('x') ?><span>Cancelar configuração</span></button></form>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                    <?php if (!$twoFactorSetupSecret || $twoFactorEmailSetupPending): ?>
                    <section class="settings-security-panel settings-detail-card">
                        <div class="settings-form-head"><h3>Código por e-mail</h3><p>Receba um código no e-mail da conta sempre que fizer login.</p></div>
                        <?php if (!$twoFactorEmailSetupPending): ?>
                            <form action="/?route=settings.2fa.email.prepare" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="btn btn-primary" type="submit"><?= icon('mail') ?><span>Enviar código de ativação</span></button>
                            </form>
                        <?php else: ?>
                            <form class="company-form settings-security-form" action="/?route=settings.2fa.email.enable" method="post" novalidate data-two-factor-password-modal-form>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <label class="field"><span>Código recebido por e-mail</span><input type="text" name="email_two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                                <button class="btn btn-primary" type="button" data-two-factor-password-modal-open><?= icon('check-circle') ?><span>Ativar por e-mail</span></button>

                                <div class="company-modal two-factor-disable-modal" data-two-factor-password-modal hidden>
                                    <div class="company-modal-dialog confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="two-factor-email-enable-title">
                                        <header class="modal-head">
                                            <div>
                                                <span class="eyebrow">Confirmação</span>
                                                <h2 id="two-factor-email-enable-title">Ativar 2FA por e-mail</h2>
                                            </div>
                                            <button class="icon-btn" type="button" data-two-factor-password-modal-close aria-label="Fechar"><?= icon('x') ?></button>
                                        </header>
                                        <div class="confirm-modal-body two-factor-disable-body">
                                            <p>Informe sua senha atual para confirmar a ativação da autenticação por e-mail.</p>
                                            <label class="field">
                                                <span>Senha atual</span>
                                                <input type="password" name="password" autocomplete="current-password" required disabled data-two-factor-password-modal-input>
                                            </label>
                                        </div>
                                        <div class="form-actions confirm-modal-actions">
                                            <button class="btn btn-muted" type="button" data-two-factor-password-modal-close>Cancelar</button>
                                            <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Ativar</span></button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            <form action="/?route=settings.2fa.cancel" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="btn btn-muted" type="submit"><?= icon('x') ?><span>Voltar para escolha do método</span></button>
                            </form>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php elseif ($settingsTopic === 'session_limit'): ?>
            <div class="settings-check-list settings-detail-card">
                <div><?= icon('check-circle') ?><span>Uma conta, uma sessão</span><small>Quando este usuário entra em outro dispositivo, a sessão anterior é encerrada automaticamente.</small></div>
                <div><?= icon('check-circle') ?><span>Sessão atual registrada</span><small><?= $sessionStartedAt ? e((string) $sessionStartedAt) : 'Será registrada no próximo login.' ?></small></div>
            </div>
        <?php elseif ($settingsTopic === 'security'): ?>
            <div class="settings-security-layout">
                <div class="settings-security-row">
                    <section class="settings-security-card">
                        <div class="settings-form-head"><h3>Sessões ativas</h3><p>O sistema permite apenas uma sessão ativa por usuário.</p></div>
                        <div class="settings-session-list">
                            <?php if (!$activeSessions): ?><p class="muted-text">Nenhuma sessão ativa registrada.</p><?php else: ?><?php foreach ($activeSessions as $session): ?>
                                <div><?= icon('check-circle') ?><span>Sessão atual</span><small>Início: <?= e((string) ($session['started_at'] ?? 'Não registrado')) ?> · IP: <?= e((string) ($session['ip_address'] ?? 'Não registrado')) ?></small><small class="settings-session-user-agent" title="<?= e((string) ($session['user_agent'] ?? '')) ?>">Navegador: <?= e((string) ($session['user_agent'] ?? 'Não registrado')) ?></small></div>
                            <?php endforeach; ?><?php endif; ?>
                        </div>
                        <form action="/?route=settings.sessions.endOther" method="post" data-confirm="Encerrar outras sessões e manter apenas esta?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-warning" type="submit"><?= icon('log-out') ?><span>Encerrar outras sessões</span></button></form>
                    </section>
                    <section class="settings-security-card">
                        <form class="company-form settings-security-form settings-security-form-compact" action="/?route=settings.security.update" method="post" novalidate data-account-confirmation-form data-vault-password-enabled="<?= $vaultRequirePasswordReveal ? '1' : '0' ?>">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="settings-form-head"><h3>Regras de segurança</h3><p>Defina expiração da sessão e reforço para revelar senhas do cofre.</p></div>
                            <label class="field"><span>Tempo de expiração da sessão</span><select name="session_timeout_minutes" required><?php foreach ([30 => '30 minutos', 60 => '1 hora', 120 => '2 horas', 240 => '4 horas', 480 => '8 horas', 720 => '12 horas', 1440 => '24 horas'] as $minutes => $label): ?><option value="<?= $minutes ?>" <?= $sessionTimeoutMinutes === $minutes ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <?php if (can_access_vault()): ?>
                            <label class="toggle-field settings-security-toggle"><input type="checkbox" name="vault_require_password_reveal" value="1" <?= $vaultRequirePasswordReveal ? 'checked' : '' ?>><span>Exigir senha para revelar credenciais do cofre</span></label>
                            <?php endif; ?>
                            <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar segurança</span></button>
                            <?php
                            $confirmationId = 'account-vault';
                            $confirmationTitle = 'Desativar confirmação de senha';
                            $confirmationDescription = 'Confirme sua senha para permitir a revelação de credenciais sem pedir a senha da conta.';
                            require BASE_PATH . '/views/partials/account-password-modal.php';
                            ?>
                        </form>
                    </section>
                </div>
                <section class="settings-security-card">
                    <div class="settings-form-head"><h3>Últimos acessos</h3><p>Eventos recentes de entrada e saída da sua conta.</p></div>
                    <div class="settings-access-list settings-access-list-compact">
                        <?php if (!$recentAccesses): ?><p class="muted-text">Nenhum acesso recente registrado.</p><?php else: ?><?php foreach ($recentAccesses as $access): ?><div><strong><?= e((string) ($access['description'] ?? $access['action_type'] ?? 'Acesso')) ?></strong><span><?= e((string) ($access['created_at'] ?? '')) ?> · <?= e((string) ($access['ip_address'] ?? 'IP não registrado')) ?></span></div><?php endforeach; ?><?php endif; ?>
                    </div>
                </section>
            </div>
        <?php elseif ($settingsTopic === 'audit' && can_permission('settings.manage')): ?>
            <div class="settings-security-layout">
                <section class="settings-security-card">
                    <form class="company-form settings-security-form settings-security-form-compact" action="/?route=settings.audit.update" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="settings-form-head">
                            <h3>Retenção de logs</h3>
                            <p>Defina por quanto tempo os registros de auditoria devem permanecer no banco.</p>
                        </div>
                        <label class="field">
                            <span>Manter logs por</span>
                            <select name="audit_retention_days" required>
                                <?php foreach ([30 => '30 dias', 60 => '60 dias', 90 => '90 dias', 180 => '180 dias', 365 => '1 ano', 730 => '2 anos', 1095 => '3 anos'] as $days => $label): ?>
                                    <option value="<?= $days ?>" <?= $auditRetentionDays === $days ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar retenção</span></button>
                    </form>
                </section>

                <section class="settings-security-card">
                    <div class="settings-form-head">
                        <h3>Exportar logs por período</h3>
                        <p>Use a tela de auditoria para filtrar por usuário, empresa, módulo, ação, criticidade e data.</p>
                    </div>
                    <div class="maintenance-action-list">
                        <a class="btn btn-muted" href="/?route=audit.index"><?= icon('filter') ?><span>Abrir filtros</span></a>
                        <a class="btn btn-primary" href="<?= e(export_url('audit', 'csv', ['date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d')])) ?>"><?= icon('file-spreadsheet') ?><span>Exportar mês em CSV</span></a>
                    </div>
                </section>

                <section class="settings-security-card">
                    <div class="settings-form-head">
                        <h3>Eventos críticos</h3>
                        <p>Login, exclusões, alteração de senha, revelação de senha, importação de banco e limpeza de arquivos são marcados como críticos.</p>
                    </div>
                    <div class="settings-check-list">
                        <div><?= icon('warning') ?><span>Criticidade visível nos logs</span><small>A tela de auditoria exibe selo crítico e permite filtrar somente esses eventos.</small></div>
                    </div>
                </section>

                <section class="settings-security-card">
                    <div class="settings-form-head">
                        <h3>Limpeza por retenção</h3>
                        <p>Remove registros mais antigos que o período configurado. A limpeza também fica registrada nos logs.</p>
                    </div>
                    <form action="/?route=settings.audit.cleanup" method="post" data-confirm="Remover logs mais antigos que a retenção configurada?" data-confirm-variant="warning">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-warning" type="submit"><?= icon('trash-2') ?><span>Limpar logs antigos</span></button>
                    </form>
                </section>
            </div>
        <?php elseif ($settingsTopic === 'devices' && can_permission('settings.manage')): ?>
            <form class="company-form settings-security-form settings-detail-card settings-admin-form" action="/?route=settings.devices.update" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="settings-form-head">
                    <h3>Prefixos de etiqueta</h3>
                    <p>Edite apenas o prefixo do tipo. As iniciais da empresa entram automaticamente.</p>
                </div>
                <div class="settings-config-grid">
                    <?php foreach ($deviceTypes as $type => $label): ?>
                        <?php
                        $template = (string) ($deviceSettings['label_prefixes'][$type] ?? '');
                        $hasCompanyInitials = $usesCompanyInitials($template);
                        ?>
                        <label class="settings-prefix-card">
                            <span class="settings-prefix-title"><?= e($label) ?></span>
                            <span class="settings-prefix-row">
                                <span class="settings-prefix-label">Prefixo</span>
                                <input type="text" name="label_prefixes[<?= e($type) ?>]" value="<?= e($editableLabelPrefix($template)) ?>" maxlength="12" placeholder="Ex.: N">
                            </span>
                            <?php if ($hasCompanyInitials): ?>
                                <span class="settings-prefix-note">+ iniciais da empresa</span>
                                <input type="hidden" name="label_prefix_uses_company[<?= e($type) ?>]" value="1">
                            <?php else: ?>
                                <span class="settings-prefix-note muted">Sem iniciais automáticas</span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="settings-rule-section">
                    <div class="settings-form-head">
                        <h3>Categorias padrão</h3>
                        <p>Defina quais tipos aparecem como categorias operacionais para novos cadastros.</p>
                    </div>
                    <div class="settings-checkbox-grid">
                        <?php foreach ($deviceTypes as $type => $label): ?>
                            <label class="toggle-field">
                                <input type="checkbox" name="default_categories[]" value="<?= e($type) ?>" <?= in_array($type, (array) $deviceSettings['default_categories'], true) ? 'checked' : '' ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-rule-section">
                    <div class="settings-form-head">
                        <h3>Campos obrigatórios por tipo</h3>
                        <p>Controle quais campos bloqueiam o salvamento de cada equipamento.</p>
                    </div>
                    <div class="settings-required-list">
                        <?php foreach ($deviceTypes as $type => $label): ?>
                            <section class="settings-mini-card">
                                <h4><?= e($label) ?></h4>
                                <div class="settings-checkbox-grid compact">
                                    <?php foreach ($deviceFieldLabels as $field => $fieldLabel): ?>
                                        <label class="toggle-field">
                                            <input type="checkbox" name="required_fields[<?= e($type) ?>][]" value="<?= e($field) ?>" <?= in_array($field, (array) ($deviceSettings['required_fields'][$type] ?? []), true) ? 'checked' : '' ?>>
                                            <span><?= e($fieldLabel) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-rule-section">
                    <div class="settings-form-head">
                        <h3>Fotos e anexos</h3>
                        <p>Defina limite de tamanho e formatos permitidos para uploads.</p>
                    </div>
                    <div class="settings-config-grid">
                        <label class="field">
                            <span>Limite de fotos</span>
                            <select name="photo_max_mb" required>
                                <?php foreach ([1, 2, 5, 10, 15, 20, 25] as $mb): ?>
                                    <option value="<?= $mb ?>" <?= (int) $deviceSettings['photo_max_mb'] === $mb ? 'selected' : '' ?>><?= $mb ?> MB</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Limite de anexos</span>
                            <select name="attachment_max_mb" required>
                                <?php foreach ([1, 2, 5, 10, 15, 20, 25] as $mb): ?>
                                    <option value="<?= $mb ?>" <?= (int) $deviceSettings['attachment_max_mb'] === $mb ? 'selected' : '' ?>><?= $mb ?> MB</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <div class="settings-two-column">
                        <section class="settings-mini-card">
                            <h4>Fotos permitidas</h4>
                            <div class="settings-checkbox-grid compact">
                                <?php foreach ($photoMimeLabels as $mime => $label): ?>
                                    <label class="toggle-field">
                                        <input type="checkbox" name="photo_mimes[]" value="<?= e($mime) ?>" <?= in_array($mime, (array) $deviceSettings['photo_mimes'], true) ? 'checked' : '' ?>>
                                        <span><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <section class="settings-mini-card">
                            <h4>Anexos permitidos</h4>
                            <div class="settings-checkbox-grid compact">
                                <?php foreach ($attachmentExtensionLabels as $extension => $label): ?>
                                    <label class="toggle-field">
                                        <input type="checkbox" name="attachment_extensions[]" value="<?= e($extension) ?>" <?= in_array($extension, (array) $deviceSettings['attachment_extensions'], true) ? 'checked' : '' ?>>
                                        <span><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar empresas e dispositivos</span></button>
            </form>
        <?php elseif ($settingsTopic === 'vault' && can_permission('vault.configure')): ?>
            <form class="company-form settings-security-form settings-detail-card settings-admin-form vault-settings-form" action="/?route=settings.vault.update" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="settings-form-head vault-default-head vault-settings-intro">
                    <div>
                        <h3>Categorias padrão do cofre</h3>
                        <p>Configure nome, ícone e subcategorias. Ao salvar, o sistema cria ou atualiza os itens padrão.</p>
                    </div>
                    <button class="btn btn-muted" type="button" data-vault-add-category>
                        <?= icon('plus') ?><span>Adicionar categoria</span>
                    </button>
                </div>
                <div class="vault-default-category-list" data-vault-category-list>
                    <?php foreach ((array) $vaultSettings['default_categories'] as $categoryIndex => $category): ?>
                        <section class="vault-default-category-card" data-vault-default-category>
                            <div class="vault-default-category-main vault-category-editor">
                                <span class="vault-default-icon-preview"><?= icon((string) ($category['icon'] ?? 'folder')) ?></span>
                                <label class="field">
                                    <span>Categoria</span>
                                    <input type="text" name="vault_categories[<?= (int) $categoryIndex ?>][name]" value="<?= e((string) ($category['name'] ?? '')) ?>" maxlength="120" required>
                                </label>
                                <label class="field">
                                    <span>Ícone</span>
                                    <select name="vault_categories[<?= (int) $categoryIndex ?>][icon]" data-vault-icon-select>
                                        <?php foreach ($vaultIconOptions as $iconKey => $iconLabel): ?>
                                            <option value="<?= e($iconKey) ?>" data-icon-markup="<?= e(icon($iconKey)) ?>" <?= (string) ($category['icon'] ?? 'folder') === $iconKey ? 'selected' : '' ?>><?= e($iconLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button class="icon-btn danger" type="button" data-vault-remove-category aria-label="Remover categoria" title="Remover categoria"><?= icon('trash-2') ?></button>
                            </div>
                            <?php $renderVaultCustomFields('vault_categories[' . (int) $categoryIndex . ']', (array) ($category['fields'] ?? [])); ?>
                            <details class="vault-default-subcategory-block">
                                <summary class="vault-default-subcategory-head">
                                    <strong>Subcategorias padrão</strong>
                                    <span class="vault-collapse-chevron" aria-hidden="true"><?= icon('chevron-down') ?></span>
                                </summary>
                                <div class="vault-default-subcategory-content">
                                    <button class="btn btn-muted compact-btn" type="button" data-vault-add-subcategory><?= icon('plus') ?><span>Adicionar subcategoria</span></button>
                                    <div class="vault-default-subcategory-list" data-vault-subcategory-list>
                                    <?php foreach ((array) ($category['children'] ?? []) as $childIndex => $child): ?>
                                        <div class="vault-default-subcategory-row" data-vault-default-subcategory>
                                            <div class="vault-default-subcategory-main">
                                                <span class="vault-default-icon-preview small"><?= icon((string) ($child['icon'] ?? 'folder')) ?></span>
                                                <label class="field">
                                                    <span>Subcategoria</span>
                                                    <input type="text" name="vault_categories[<?= (int) $categoryIndex ?>][children][<?= (int) $childIndex ?>][name]" value="<?= e((string) ($child['name'] ?? '')) ?>" maxlength="120" required>
                                                </label>
                                                <label class="field">
                                                    <span>Ícone</span>
                                                    <select name="vault_categories[<?= (int) $categoryIndex ?>][children][<?= (int) $childIndex ?>][icon]" data-vault-icon-select>
                                                        <?php foreach ($vaultIconOptions as $iconKey => $iconLabel): ?>
                                                            <option value="<?= e($iconKey) ?>" data-icon-markup="<?= e(icon($iconKey)) ?>" <?= (string) ($child['icon'] ?? 'folder') === $iconKey ? 'selected' : '' ?>><?= e($iconLabel) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <button class="icon-btn danger" type="button" data-vault-remove-subcategory aria-label="Remover subcategoria" title="Remover subcategoria"><?= icon('trash-2') ?></button>
                                            </div>
                                            <?php $renderVaultCustomFields('vault_categories[' . (int) $categoryIndex . '][children][' . (int) $childIndex . ']', (array) ($child['fields'] ?? [])); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            </details>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="settings-rule-section vault-access-rules">
                    <div class="settings-form-head">
                        <h3>Regras de acesso às senhas</h3>
                        <p>Controle cópia, confirmação, auditoria e alerta de credenciais antigas.</p>
                    </div>
                    <div class="settings-check-list">
                        <label class="toggle-field">
                            <input type="checkbox" name="allow_password_copy" value="1" <?= !empty($vaultSettings['allow_password_copy']) ? 'checked' : '' ?>>
                            <span>Permitir botão de copiar senha</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="require_reveal_confirmation" value="1" <?= !empty($vaultSettings['require_reveal_confirmation']) ? 'checked' : '' ?>>
                            <span>Exigir confirmação antes de revelar senha</span>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="audit_secret_access" value="1" checked disabled>
                            <span>Registrar visualização e cópia de senha nos logs</span>
                        </label>
                    </div>
                    <label class="field settings-expiration-field">
                        <span>Alerta de credencial antiga</span>
                        <select name="credential_expiration_days" required>
                            <?php foreach ([0 => 'Desativado', 30 => '30 dias', 60 => '60 dias', 90 => '90 dias', 180 => '180 dias', 365 => '1 ano', 730 => '2 anos', 1095 => '3 anos'] as $days => $label): ?>
                                <option value="<?= $days ?>" <?= (int) $vaultSettings['credential_expiration_days'] === $days ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <button class="btn btn-primary vault-save-button" type="submit"><?= icon('save') ?><span>Salvar cofre de senhas</span></button>
            </form>
            <template data-vault-category-template>
                <section class="vault-default-category-card" data-vault-default-category>
                    <div class="vault-default-category-main vault-category-editor">
                        <span class="vault-default-icon-preview"><?= icon('folder') ?></span>
                        <label class="field">
                            <span>Categoria</span>
                            <input type="text" name="vault_categories[__CATEGORY__][name]" value="" maxlength="120" required>
                        </label>
                        <label class="field">
                            <span>Ícone</span>
                            <select name="vault_categories[__CATEGORY__][icon]" data-vault-icon-select>
                                <?php foreach ($vaultIconOptions as $iconKey => $iconLabel): ?>
                                    <option value="<?= e($iconKey) ?>" data-icon-markup="<?= e(icon($iconKey)) ?>" <?= $iconKey === 'folder' ? 'selected' : '' ?>><?= e($iconLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="icon-btn danger" type="button" data-vault-remove-category aria-label="Remover categoria" title="Remover categoria"><?= icon('trash-2') ?></button>
                    </div>
                    <?php $renderVaultCustomFields('vault_categories[__CATEGORY__]', []); ?>
                    <details class="vault-default-subcategory-block">
                        <summary class="vault-default-subcategory-head">
                            <strong>Subcategorias padrão</strong>
                            <span class="vault-collapse-chevron" aria-hidden="true"><?= icon('chevron-down') ?></span>
                        </summary>
                        <div class="vault-default-subcategory-content">
                            <button class="btn btn-muted compact-btn" type="button" data-vault-add-subcategory><?= icon('plus') ?><span>Adicionar subcategoria</span></button>
                            <div class="vault-default-subcategory-list" data-vault-subcategory-list></div>
                        </div>
                    </details>
                </section>
            </template>
            <template data-vault-subcategory-template>
                <div class="vault-default-subcategory-row" data-vault-default-subcategory>
                    <div class="vault-default-subcategory-main">
                        <span class="vault-default-icon-preview small"><?= icon('folder') ?></span>
                        <label class="field">
                            <span>Subcategoria</span>
                            <input type="text" name="vault_categories[__CATEGORY__][children][__CHILD__][name]" value="" maxlength="120" required>
                        </label>
                        <label class="field">
                            <span>Ícone</span>
                            <select name="vault_categories[__CATEGORY__][children][__CHILD__][icon]" data-vault-icon-select>
                                <?php foreach ($vaultIconOptions as $iconKey => $iconLabel): ?>
                                    <option value="<?= e($iconKey) ?>" data-icon-markup="<?= e(icon($iconKey)) ?>" <?= $iconKey === 'folder' ? 'selected' : '' ?>><?= e($iconLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="icon-btn danger" type="button" data-vault-remove-subcategory aria-label="Remover subcategoria" title="Remover subcategoria"><?= icon('trash-2') ?></button>
                    </div>
                    <?php $renderVaultCustomFields('vault_categories[__CATEGORY__][children][__CHILD__]', []); ?>
                </div>
            </template>
            <template data-vault-custom-field-template>
                <div class="vault-default-field-row" data-vault-custom-field>
                    <input type="hidden" name="__BASE__[fields][__FIELD__][key]" value="">
                    <label class="field">
                        <span>Nome do campo</span>
                        <input type="text" name="__BASE__[fields][__FIELD__][label]" value="" maxlength="80" placeholder="Ex.: IP de acesso">
                    </label>
                    <label class="field">
                        <span>Tipo</span>
                        <select name="__BASE__[fields][__FIELD__][type]">
                            <?php foreach ($vaultCustomFieldTypeLabels as $type => $label): ?>
                                <option value="<?= e($type) ?>"><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="toggle-field vault-default-field-required">
                        <input type="checkbox" name="__BASE__[fields][__FIELD__][required]" value="1">
                        <span>Obrigatório</span>
                    </label>
                    <button class="icon-btn danger" type="button" data-vault-remove-custom-field aria-label="Remover campo" title="Remover campo"><?= icon('trash-2') ?></button>
                </div>
            </template>
        <?php elseif ($settingsTopic === 'maintenance' && is_array($maintenanceStatus)): ?>
            <div class="maintenance-grid">
                <section class="settings-security-card maintenance-status-card">
                    <div class="settings-form-head"><h3>Status do banco</h3><p><?= e((string) $maintenanceStatus['database_name']) ?> · <?= count($maintenanceStatus['tables']) ?> tabela(s)</p></div>
                    <div class="maintenance-metrics">
                        <div><span>Tamanho do banco</span><strong><?= e(format_file_size((int) $maintenanceStatus['database_size'])) ?></strong></div>
                        <div><span>Fotos</span><strong><?= (int) $maintenanceStatus['machine_photos']['files'] ?></strong><small><?= e(format_file_size((int) $maintenanceStatus['machine_photos']['bytes'])) ?></small></div>
                        <div><span>Anexos</span><strong><?= (int) $maintenanceStatus['company_attachments']['files'] ?></strong><small><?= e(format_file_size((int) $maintenanceStatus['company_attachments']['bytes'])) ?></small></div>
                        <div><span>Órfãos</span><strong><?= (int) $maintenanceStatus['orphans']['total'] ?></strong><small><?= e(format_file_size((int) $maintenanceStatus['orphans']['bytes'])) ?></small></div>
                    </div>
                </section>
                <?php if (can_permission('settings.backup')): ?><section class="settings-security-card"><div class="settings-form-head"><h3>Exportações</h3><p>Baixe uma cópia do banco ou um pacote completo com arquivos.</p></div><div class="maintenance-action-list"><a class="btn btn-primary" href="/?route=maintenance.exportCleanDatabase"><?= icon('download') ?><span>Exportar banco limpo</span></a><a class="btn btn-muted" href="/?route=maintenance.exportFullBackup"><?= icon('download') ?><span>Exportar backup completo</span></a></div></section><?php endif; ?>
                <?php if (can_permission('settings.restore')): ?><section class="settings-security-card"><form class="company-form settings-maintenance-form" action="/?route=maintenance.importDatabase" method="post" enctype="multipart/form-data" data-confirm="Importar este SQL pode substituir dados atuais. Confirma a importação?" data-confirm-variant="warning" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="settings-form-head"><h3>Importar backup</h3><p>Use apenas arquivos SQL gerados por este sistema.</p></div><label class="field"><span>Arquivo SQL</span><input type="file" name="backup_sql" accept=".sql,.exe-sql" required></label><button class="btn btn-warning" type="submit"><?= icon('upload') ?><span>Importar SQL</span></button></form></section><?php endif; ?>
                <?php if (can_permission('settings.restore')): ?><section class="settings-security-card"><div class="settings-form-head"><h3>Arquivos órfãos</h3><p>Remove fotos e anexos que existem na pasta, mas não possuem vínculo no banco.</p></div><form action="/?route=maintenance.cleanupOrphans" method="post" data-confirm="Remover arquivos órfãos encontrados no storage?" data-confirm-variant="warning"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-warning" type="submit" <?= (int) $maintenanceStatus['orphans']['total'] === 0 ? 'disabled' : '' ?>><?= icon('trash-2') ?><span>Limpar arquivos órfãos</span></button></form></section><?php endif; ?>
            </div>
            <section class="settings-security-card maintenance-table-card">
                <div class="settings-form-head"><h3>Tabelas do banco</h3><p>Resumo rápido para acompanhar volume e espaço ocupado.</p></div>
                <div class="table-scroll"><table class="inventory-table"><thead><tr><th>Tabela</th><th>Registros estimados</th><th>Tamanho</th></tr></thead><tbody><?php foreach ($maintenanceStatus['tables'] as $table): ?><tr><td data-label="Tabela"><strong><?= e((string) $table['name']) ?></strong></td><td data-label="Registros estimados"><?= (int) $table['rows'] ?></td><td data-label="Tamanho"><?= e(format_file_size((int) $table['bytes'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
        <?php endif; ?>
    </section>
<?php endif; ?>
