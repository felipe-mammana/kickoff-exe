<?php
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
$apiTokens = $apiTokens ?? [];
$generatedApiToken = $generatedApiToken ?? null;
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <strong>Configurações</strong>
</nav>

<section class="asset-page-head">
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

<section class="audit-summary-grid settings-summary-grid">
    <article class="summary-card">
        <span class="summary-icon"><?= icon('user') ?></span>
        <div>
            <strong><?= e($accountUser['name']) ?></strong>
            <span><?= e($accountUser['email']) ?></span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('shield') ?></span>
        <div>
            <strong><?= $twoFactorEnabled ? 'Ativo' : 'Inativo' ?></strong>
            <span>2FA da conta</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('history') ?></span>
        <div>
            <strong>1 sessão</strong>
            <span>limite simultâneo</span>
        </div>
    </article>
</section>

<section class="settings-grid">
    <article class="asset-panel settings-topic-panel settings-wide-panel" data-settings-topic>
        <header class="asset-panel-head">
            <div>
                <span><?= icon('user') ?></span>
                <div>
                    <h2>Conta</h2>
                    <p>Dados do perfil e troca da senha de acesso.</p>
                </div>
            </div>
            <button class="btn btn-muted" type="button" data-settings-topic-toggle aria-expanded="false">
                <?= icon('settings') ?><span>Abrir configurações</span>
            </button>
        </header>
        <div class="settings-topic-body" data-settings-topic-body hidden>
            <div class="settings-account-grid">
                <form class="company-form settings-security-form" action="/?route=settings.profile.update" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="settings-form-head">
                        <h3>Perfil da conta</h3>
                        <p>Atualize nome e e-mail do usuário conectado.</p>
                    </div>
                    <label class="field">
                        <span>Nome</span>
                        <input type="text" name="name" value="<?= e($accountUser['name']) ?>" maxlength="120" required>
                    </label>
                    <label class="field">
                        <span>E-mail</span>
                        <input type="email" name="email" value="<?= e($accountUser['email']) ?>" maxlength="160" required>
                    </label>
                    <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar perfil</span></button>
                </form>

                <form class="company-form settings-security-form" action="/?route=settings.password.update" method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="settings-form-head">
                        <h3>Alterar senha</h3>
                        <p>Troque a senha usada para entrar no sistema.</p>
                    </div>
                    <label class="field">
                        <span>Senha atual</span>
                        <input type="password" name="current_password" autocomplete="current-password" required>
                    </label>
                    <label class="field">
                        <span>Nova senha</span>
                        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                    </label>
                    <label class="field">
                        <span>Confirmar nova senha</span>
                        <input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
                    </label>
                    <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Alterar senha</span></button>
                </form>
            </div>
        </div>
    </article>

    <article class="asset-panel settings-topic-panel settings-wide-panel" data-settings-topic>
        <header class="asset-panel-head">
            <div>
                <span><?= icon('settings') ?></span>
                <div>
                    <h2>Preferências do sistema</h2>
                    <p>Tema, menu lateral, tabelas e formato de data.</p>
                </div>
            </div>
            <button class="btn btn-muted" type="button" data-settings-topic-toggle aria-expanded="false">
                <?= icon('settings') ?><span>Abrir configurações</span>
            </button>
        </header>
        <div class="settings-topic-body" data-settings-topic-body hidden>
            <form class="company-form settings-security-form settings-preferences-form" action="/?route=settings.preferences.update" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label class="field">
                    <span>Tema padrão</span>
                    <select name="preferred_theme" required>
                        <option value="light" <?= $preferredTheme === 'light' ? 'selected' : '' ?>>Claro</option>
                        <option value="dark" <?= $preferredTheme === 'dark' ? 'selected' : '' ?>>Escuro</option>
                    </select>
                </label>
                <label class="field">
                    <span>Menu no computador</span>
                    <select name="sidebar_default" required>
                        <option value="expanded" <?= $sidebarDefault === 'expanded' ? 'selected' : '' ?>>Aberto</option>
                        <option value="collapsed" <?= $sidebarDefault === 'collapsed' ? 'selected' : '' ?>>Recolhido</option>
                    </select>
                </label>
                <label class="field">
                    <span>Itens por tabela</span>
                    <select name="table_page_size" required>
                        <?php foreach ([10, 25, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $tablePageSize === $size ? 'selected' : '' ?>><?= $size ?> itens</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Formato de data</span>
                    <select name="datetime_format" required>
                        <option value="d/m/Y H:i" <?= $datetimeFormat === 'd/m/Y H:i' ? 'selected' : '' ?>>31/08/2026 14:30</option>
                        <option value="d/m/Y" <?= $datetimeFormat === 'd/m/Y' ? 'selected' : '' ?>>31/08/2026</option>
                        <option value="Y-m-d H:i" <?= $datetimeFormat === 'Y-m-d H:i' ? 'selected' : '' ?>>2026-08-31 14:30</option>
                        <option value="Y-m-d" <?= $datetimeFormat === 'Y-m-d' ? 'selected' : '' ?>>2026-08-31</option>
                    </select>
                </label>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar preferências</span></button>
            </form>
        </div>
    </article>

    <article class="asset-panel settings-topic-panel settings-wide-panel" data-settings-topic>
        <header class="asset-panel-head">
            <div>
                <span><?= icon('shield') ?></span>
                <div>
                    <h2>Autenticação em dois fatores</h2>
                    <p>Senha, aplicativo autenticador e código por e-mail no login.</p>
                </div>
            </div>
            <div class="settings-head-actions">
                <span class="status-chip <?= $twoFactorEnabled ? 'success' : 'neutral' ?>"><?= $twoFactorEnabled ? 'Ativo' : 'Inativo' ?></span>
                <button class="btn btn-muted" type="button" data-settings-topic-toggle aria-expanded="false">
                    <?= icon('settings') ?><span>Abrir configurações</span>
                </button>
            </div>
        </header>
        <div class="settings-topic-body" data-settings-topic-body hidden>
            <?php if ($twoFactorEnabled): ?>
                <div class="settings-security-panel">
                    <div class="settings-readonly">
                        <p>O 2FA está ativo. No login, o sistema aceita o código do aplicativo autenticador ou um código enviado por e-mail.</p>
                    </div>
                    <form action="/?route=settings.2fa.email.test" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-muted" type="submit"><?= icon('mail') ?><span>Testar código por e-mail</span></button>
                    </form>
                    <form class="company-form settings-security-form" action="/?route=settings.2fa.disable" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <label class="field">
                            <span>Senha atual</span>
                            <input type="password" name="password" autocomplete="current-password" required>
                        </label>
                        <label class="field">
                            <span>Código 2FA</span>
                            <input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                        </label>
                        <button class="btn btn-danger" type="submit"><?= icon('trash-2') ?><span>Desativar 2FA</span></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="settings-security-panel">
                    <?php if (!$twoFactorSetupSecret): ?>
                        <div class="settings-readonly">
                            <p>Para ativar, gere uma chave, cadastre no aplicativo autenticador e confirme o primeiro código.</p>
                        </div>
                        <form action="/?route=settings.2fa.prepare" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Configurar 2FA</span></button>
                        </form>
                    <?php else: ?>
                        <div class="two-factor-setup">
                            <div>
                                <span class="eyebrow">Chave manual</span>
                                <code><?= e($twoFactorSetupSecret) ?></code>
                                <small>Adicione esta chave no aplicativo autenticador e informe o código gerado.</small>
                            </div>
                            <?php if ($twoFactorProvisioningUri): ?>
                                <div>
                                    <span class="eyebrow">URI de configuração</span>
                                    <code><?= e($twoFactorProvisioningUri) ?></code>
                                    <small>Use apenas se o aplicativo permitir colar uma URI otpauth.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <form class="company-form settings-security-form" action="/?route=settings.2fa.enable" method="post" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <label class="field">
                                <span>Senha atual</span>
                                <input type="password" name="password" autocomplete="current-password" required>
                            </label>
                            <label class="field">
                                <span>Código 2FA</span>
                                <input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required>
                            </label>
                            <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Ativar 2FA</span></button>
                        </form>
                        <form action="/?route=settings.2fa.cancel" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-muted" type="submit"><?= icon('x') ?><span>Cancelar configuração</span></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="asset-panel settings-topic-panel" data-settings-topic>
        <header class="asset-panel-head">
            <div>
                <span><?= icon('history') ?></span>
                <div>
                    <h2>Limite de sessão</h2>
                    <p>Apenas uma sessão ativa por usuário.</p>
                </div>
            </div>
            <div class="settings-head-actions">
                <span class="status-chip success">Ativo</span>
                <button class="btn btn-muted" type="button" data-settings-topic-toggle aria-expanded="false">
                    <?= icon('settings') ?><span>Abrir configurações</span>
                </button>
            </div>
        </header>
        <div class="settings-topic-body" data-settings-topic-body hidden>
            <div class="settings-check-list">
                <div>
                    <?= icon('check-circle') ?>
                    <span>Uma conta, uma sessão</span>
                    <small>Quando este usuário entra em outro dispositivo, a sessão anterior é encerrada automaticamente.</small>
                </div>
                <div>
                    <?= icon('check-circle') ?>
                    <span>Sessão atual registrada</span>
                    <small><?= $sessionStartedAt ? e((string) $sessionStartedAt) : 'Será registrada no próximo login.' ?></small>
                </div>
            </div>
        </div>
    </article>

    <article class="asset-panel settings-topic-panel" data-settings-topic>
        <header class="asset-panel-head">
            <div>
                <span><?= icon('file-clock') ?></span>
                <div>
                    <h2>Segurança</h2>
                    <p>Sessões, acessos, expiração, tokens e proteção do cofre.</p>
                </div>
            </div>
            <div class="settings-head-actions">
                <?php if (is_admin()): ?>
                    <a class="link-primary" href="/?route=audit.index">Ver logs</a>
                <?php endif; ?>
                <button class="btn btn-muted" type="button" data-settings-topic-toggle aria-expanded="false">
                    <?= icon('settings') ?><span>Abrir configurações</span>
                </button>
            </div>
        </header>
        <div class="settings-topic-body" data-settings-topic-body hidden>
            <div class="settings-security-grid">
                <section class="settings-security-card">
                    <div class="settings-form-head">
                        <h3>Sessões ativas</h3>
                        <p>O sistema permite apenas uma sessão ativa por usuário.</p>
                    </div>
                    <div class="settings-session-list">
                        <?php if (!$activeSessions): ?>
                            <p class="muted-text">Nenhuma sessão ativa registrada.</p>
                        <?php else: ?>
                            <?php foreach ($activeSessions as $session): ?>
                                <div>
                                    <?= icon('check-circle') ?>
                                    <span>Sessão atual</span>
                                    <small>
                                        Início: <?= e((string) ($session['started_at'] ?? 'Não registrado')) ?>
                                        · IP: <?= e((string) ($session['ip_address'] ?? 'Não registrado')) ?>
                                    </small>
                                    <small title="<?= e((string) ($session['user_agent'] ?? '')) ?>">
                                        Navegador: <?= e((string) ($session['user_agent'] ?? 'Não registrado')) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <form action="/?route=settings.sessions.endOther" method="post" data-confirm="Encerrar outras sessões e manter apenas esta?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="btn btn-warning" type="submit"><?= icon('log-out') ?><span>Encerrar outras sessões</span></button>
                    </form>
                </section>

                <section class="settings-security-card">
                    <div class="settings-form-head">
                        <h3>Últimos acessos</h3>
                        <p>Eventos recentes de entrada e saída da sua conta.</p>
                    </div>
                    <div class="settings-access-list">
                        <?php if (!$recentAccesses): ?>
                            <p class="muted-text">Nenhum acesso recente registrado.</p>
                        <?php else: ?>
                            <?php foreach ($recentAccesses as $access): ?>
                                <div>
                                    <strong><?= e((string) ($access['description'] ?? $access['action_type'] ?? 'Acesso')) ?></strong>
                                    <span><?= e((string) ($access['created_at'] ?? '')) ?> · <?= e((string) ($access['ip_address'] ?? 'IP não registrado')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="settings-security-card">
                    <form class="company-form settings-security-form" action="/?route=settings.security.update" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="settings-form-head">
                            <h3>Regras de segurança</h3>
                            <p>Defina expiração da sessão e reforço para revelar senhas do cofre.</p>
                        </div>
                        <label class="field">
                            <span>Tempo de expiração da sessão</span>
                            <select name="session_timeout_minutes" required>
                                <?php foreach ([30 => '30 minutos', 60 => '1 hora', 120 => '2 horas', 240 => '4 horas', 480 => '8 horas', 720 => '12 horas', 1440 => '24 horas'] as $minutes => $label): ?>
                                    <option value="<?= $minutes ?>" <?= $sessionTimeoutMinutes === $minutes ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="toggle-field">
                            <input type="checkbox" name="vault_require_password_reveal" value="1" <?= $vaultRequirePasswordReveal ? 'checked' : '' ?>>
                            <span>Exigir senha para revelar credenciais do cofre</span>
                        </label>
                        <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar segurança</span></button>
                    </form>
                </section>

                <section class="settings-security-card">
                    <form class="company-form settings-security-form" action="/?route=settings.apiTokens.store" method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="settings-form-head">
                            <h3>Tokens de API</h3>
                            <p>Gere tokens para integrações externas. O valor aparece apenas uma vez.</p>
                        </div>
                        <?php if ($generatedApiToken): ?>
                            <div class="settings-token-result">
                                <strong><?= e((string) ($generatedApiToken['name'] ?? 'Token criado')) ?></strong>
                                <code><?= e((string) ($generatedApiToken['token'] ?? '')) ?></code>
                                <small>Expira em <?= e((string) ($generatedApiToken['expires_at'] ?? '')) ?></small>
                            </div>
                        <?php endif; ?>
                        <label class="field">
                            <span>Nome do token</span>
                            <input type="text" name="api_token_name" maxlength="120" placeholder="Ex.: Integração Power BI" required>
                        </label>
                        <label class="field">
                            <span>Validade</span>
                            <select name="api_token_days" required>
                                <option value="7">7 dias</option>
                                <option value="30">30 dias</option>
                                <option value="90" selected>90 dias</option>
                                <option value="180">180 dias</option>
                                <option value="365">365 dias</option>
                            </select>
                        </label>
                        <button class="btn btn-primary" type="submit"><?= icon('plus') ?><span>Gerar token</span></button>
                    </form>

                    <div class="settings-token-list">
                        <?php if (!$apiTokens): ?>
                            <p class="muted-text">Nenhum token criado.</p>
                        <?php else: ?>
                            <?php foreach ($apiTokens as $token): ?>
                                <div>
                                    <span>
                                        <strong><?= e($token['name']) ?></strong>
                                        <small>Criado em <?= e((string) $token['created_at']) ?> · expira em <?= e((string) ($token['expires_at'] ?: 'Nunca')) ?></small>
                                    </span>
                                    <?php if (empty($token['revoked_at'])): ?>
                                        <form action="/?route=settings.apiTokens.revoke" method="post" data-confirm="Revogar este token de API?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $token['id'] ?>">
                                            <button class="icon-btn danger" type="submit" aria-label="Revogar token" title="Revogar token"><?= icon('trash-2') ?></button>
                                        </form>
                                    <?php else: ?>
                                        <span class="status-chip neutral">Revogado</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </article>
</section>
