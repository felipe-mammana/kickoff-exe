<?php
$settingsTopic = $settingsTopic ?? null;
$twoFactorEnabled = !empty($accountUser['two_factor_enabled']);
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
$nameParts = preg_split('/\s+/', trim((string) $accountUser['name'])) ?: [];
$initials = strtoupper(substr($nameParts[0] ?? 'E', 0, 1) . substr($nameParts[1] ?? 'X', 0, 1));

$topics = [
    'account' => ['route' => 'settings.account', 'icon' => 'user', 'eyebrow' => 'Segurança & perfil', 'title' => 'Configurações da conta', 'card_title' => 'Conta', 'description' => 'Dados do perfil e troca da senha de acesso.'],
    'preferences' => ['route' => 'settings.preferences', 'icon' => 'settings', 'eyebrow' => 'Preferências', 'title' => 'Preferências do sistema', 'card_title' => 'Preferências do sistema', 'description' => 'Tema, menu lateral, tabelas e formato de data.'],
    'two_factor' => ['route' => 'settings.twoFactor', 'icon' => 'shield', 'eyebrow' => 'Acesso seguro', 'title' => 'Autenticação em dois fatores', 'card_title' => 'Autenticação em dois fatores', 'description' => 'Senha, aplicativo autenticador e código por e-mail no login.', 'status' => $twoFactorEnabled ? 'Ativo' : 'Inativo', 'status_class' => $twoFactorEnabled ? 'success' : 'neutral'],
    'session_limit' => ['route' => 'settings.sessionLimit', 'icon' => 'history', 'eyebrow' => 'Sessões', 'title' => 'Limite de sessão', 'card_title' => 'Limite de sessão', 'description' => 'Apenas uma sessão ativa por usuário.', 'status' => 'Ativo', 'status_class' => 'success'],
    'security' => ['route' => 'settings.security', 'icon' => 'file-clock', 'eyebrow' => 'Segurança', 'title' => 'Segurança da conta', 'card_title' => 'Segurança', 'description' => 'Sessões, acessos, expiração e proteção do cofre.'],
];

if (is_admin() && is_array($maintenanceStatus)) {
    $topics['audit'] = ['route' => 'settings.audit', 'icon' => 'file-clock', 'eyebrow' => 'Auditoria', 'title' => 'Auditoria do sistema', 'card_title' => 'Auditoria', 'description' => 'Retenção de logs, exportação por período e eventos críticos.'];
    $topics['maintenance'] = ['route' => 'settings.maintenance', 'icon' => 'database', 'eyebrow' => 'Manutenção', 'title' => 'Backup e manutenção', 'card_title' => 'Backup e manutenção', 'description' => 'Exporte backups, importe SQL e remova arquivos órfãos.'];
}

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
            <?php if (is_admin()): ?>
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
                    <?php if ($key === 'security' && is_admin()): ?>
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
            <div class="settings-account-grid">
                <form class="company-form settings-security-form settings-detail-card" action="/?route=settings.profile.update" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="settings-form-head"><h3>Perfil da conta</h3><p>Atualize nome e e-mail do usuário conectado.</p></div>
                    <label class="field"><span>Nome</span><input type="text" name="name" value="<?= e($accountUser['name']) ?>" maxlength="120" required></label>
                    <label class="field"><span>E-mail</span><input type="email" name="email" value="<?= e($accountUser['email']) ?>" maxlength="160" required></label>
                    <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar perfil</span></button>
                </form>
                <form class="company-form settings-security-form settings-detail-card" action="/?route=settings.password.update" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="settings-form-head"><h3>Alterar senha</h3><p>Troque a senha usada para entrar no sistema.</p></div>
                    <label class="field"><span>Senha atual</span><input type="password" name="current_password" autocomplete="current-password" required></label>
                    <label class="field"><span>Nova senha</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
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
                    <div class="settings-readonly"><p>O 2FA está ativo. No login, o sistema aceita o código do aplicativo autenticador ou um código enviado por e-mail.</p></div>
                    <form action="/?route=settings.2fa.email.test" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-muted" type="submit"><?= icon('mail') ?><span>Testar código por e-mail</span></button></form>
                    <form class="company-form settings-security-form" action="/?route=settings.2fa.disable" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="field"><span>Senha atual</span><input type="password" name="password" autocomplete="current-password" required></label>
                        <label class="field"><span>Código 2FA</span><input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                        <button class="btn btn-danger" type="submit"><?= icon('trash-2') ?><span>Desativar 2FA</span></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="settings-security-panel settings-detail-card">
                    <?php if (!$twoFactorSetupSecret): ?>
                        <div class="settings-readonly"><p>Para ativar, gere uma chave, cadastre no aplicativo autenticador e confirme o primeiro código.</p></div>
                        <form action="/?route=settings.2fa.prepare" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Configurar 2FA</span></button></form>
                    <?php else: ?>
                        <div class="two-factor-setup">
                            <div><span class="eyebrow">Chave manual</span><code><?= e($twoFactorSetupSecret) ?></code><small>Adicione esta chave no aplicativo autenticador e informe o código gerado.</small></div>
                            <?php if ($twoFactorProvisioningUri): ?><div><span class="eyebrow">URI de configuração</span><code><?= e($twoFactorProvisioningUri) ?></code><small>Use apenas se o aplicativo permitir colar uma URI otpauth.</small></div><?php endif; ?>
                        </div>
                        <form class="company-form settings-security-form" action="/?route=settings.2fa.enable" method="post" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <label class="field"><span>Senha atual</span><input type="password" name="password" autocomplete="current-password" required></label>
                            <label class="field"><span>Código 2FA</span><input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
                            <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Ativar 2FA</span></button>
                        </form>
                        <form action="/?route=settings.2fa.cancel" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-muted" type="submit"><?= icon('x') ?><span>Cancelar configuração</span></button></form>
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
                        <form class="company-form settings-security-form settings-security-form-compact" action="/?route=settings.security.update" method="post" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="settings-form-head"><h3>Regras de segurança</h3><p>Defina expiração da sessão e reforço para revelar senhas do cofre.</p></div>
                            <label class="field"><span>Tempo de expiração da sessão</span><select name="session_timeout_minutes" required><?php foreach ([30 => '30 minutos', 60 => '1 hora', 120 => '2 horas', 240 => '4 horas', 480 => '8 horas', 720 => '12 horas', 1440 => '24 horas'] as $minutes => $label): ?><option value="<?= $minutes ?>" <?= $sessionTimeoutMinutes === $minutes ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label class="toggle-field settings-security-toggle"><input type="checkbox" name="vault_require_password_reveal" value="1" <?= $vaultRequirePasswordReveal ? 'checked' : '' ?>><span>Exigir senha para revelar credenciais do cofre</span></label>
                            <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar segurança</span></button>
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
        <?php elseif ($settingsTopic === 'audit' && is_admin()): ?>
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
                <section class="settings-security-card"><div class="settings-form-head"><h3>Exportações</h3><p>Baixe uma cópia do banco ou um pacote completo com arquivos.</p></div><div class="maintenance-action-list"><a class="btn btn-primary" href="/?route=maintenance.exportCleanDatabase"><?= icon('download') ?><span>Exportar banco limpo</span></a><a class="btn btn-muted" href="/?route=maintenance.exportFullBackup"><?= icon('download') ?><span>Exportar backup completo</span></a></div></section>
                <section class="settings-security-card"><form class="company-form settings-maintenance-form" action="/?route=maintenance.importDatabase" method="post" enctype="multipart/form-data" data-confirm="Importar este SQL pode substituir dados atuais. Confirma a importação?" data-confirm-variant="warning" novalidate><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="settings-form-head"><h3>Importar backup</h3><p>Use apenas arquivos SQL gerados por este sistema.</p></div><label class="field"><span>Arquivo SQL</span><input type="file" name="backup_sql" accept=".sql" required></label><button class="btn btn-warning" type="submit"><?= icon('upload') ?><span>Importar SQL</span></button></form></section>
                <section class="settings-security-card"><div class="settings-form-head"><h3>Arquivos órfãos</h3><p>Remove fotos e anexos que existem na pasta, mas não possuem vínculo no banco.</p></div><form action="/?route=maintenance.cleanupOrphans" method="post" data-confirm="Remover arquivos órfãos encontrados no storage?" data-confirm-variant="warning"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-warning" type="submit" <?= (int) $maintenanceStatus['orphans']['total'] === 0 ? 'disabled' : '' ?>><?= icon('trash-2') ?><span>Limpar arquivos órfãos</span></button></form></section>
            </div>
            <section class="settings-security-card maintenance-table-card">
                <div class="settings-form-head"><h3>Tabelas do banco</h3><p>Resumo rápido para acompanhar volume e espaço ocupado.</p></div>
                <div class="table-scroll"><table class="inventory-table"><thead><tr><th>Tabela</th><th>Registros estimados</th><th>Tamanho</th></tr></thead><tbody><?php foreach ($maintenanceStatus['tables'] as $table): ?><tr><td data-label="Tabela"><strong><?= e((string) $table['name']) ?></strong></td><td data-label="Registros estimados"><?= (int) $table['rows'] ?></td><td data-label="Tamanho"><?= e(format_file_size((int) $table['bytes'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
            </section>
        <?php endif; ?>
    </section>
<?php endif; ?>
