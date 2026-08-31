<?php
$twoFactorEnabled = !empty($accountUser['two_factor_enabled']);
$sessionStartedAt = $accountUser['active_session_started_at'] ?? null;
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
    <article class="asset-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('user') ?></span>
                <div>
                    <h2>Perfil da conta</h2>
                    <p>Atualize os dados básicos do usuário conectado.</p>
                </div>
            </div>
        </header>
        <form class="company-form settings-security-form" action="/?route=settings.profile.update" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
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
    </article>

    <article class="asset-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('key-round') ?></span>
                <div>
                    <h2>Alterar senha</h2>
                    <p>Troque a senha usada para entrar no sistema.</p>
                </div>
            </div>
        </header>
        <form class="company-form settings-security-form" action="/?route=settings.password.update" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
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
    </article>

    <article class="asset-panel settings-wide-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('shield') ?></span>
                <div>
                    <h2>Autenticação em dois fatores</h2>
                    <p>Protege sua conta com um código temporário do aplicativo autenticador.</p>
                </div>
            </div>
            <span class="status-chip <?= $twoFactorEnabled ? 'success' : 'neutral' ?>"><?= $twoFactorEnabled ? 'Ativo' : 'Inativo' ?></span>
        </header>

        <?php if ($twoFactorEnabled): ?>
            <div class="settings-security-panel">
                <div class="settings-readonly">
                    <p>O 2FA está ativo. No próximo login, o sistema exigirá senha e código do aplicativo autenticador.</p>
                </div>
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
                        <p>Para ativar, gere uma chave, cadastre no Google Authenticator, Microsoft Authenticator, 1Password ou aplicativo compatível e confirme o primeiro código.</p>
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
    </article>

    <article class="asset-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('history') ?></span>
                <div>
                    <h2>Limite de sessão</h2>
                    <p>Apenas uma sessão ativa por usuário.</p>
                </div>
            </div>
            <span class="status-chip success">Ativo</span>
        </header>
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
    </article>

    <article class="asset-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('file-clock') ?></span>
                <div>
                    <h2>Segurança e auditoria</h2>
                    <p>Recursos ativos para rastreabilidade das ações principais.</p>
                </div>
            </div>
            <?php if (is_admin()): ?>
                <a class="link-primary" href="/?route=audit.index">Ver logs</a>
            <?php endif; ?>
        </header>
        <div class="settings-check-list">
            <div>
                <?= icon('check-circle') ?>
                <span>Logs do sistema ativos</span>
                <small>Login, 2FA, empresas, dispositivos, anexos e cofre</small>
            </div>
            <div>
                <?= icon('check-circle') ?>
                <span>CSRF ativo em formulários</span>
                <small>Proteção em ações de escrita</small>
            </div>
            <div>
                <?= icon('check-circle') ?>
                <span>Controle administrativo</span>
                <small>Usuários comuns continuam sem acesso a rotas restritas.</small>
            </div>
        </div>
    </article>
</section>
