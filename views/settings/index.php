<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <strong>Configuracoes</strong>
</nav>

<section class="asset-page-head">
    <div>
        <span class="eyebrow">Sistema</span>
        <h1>Configuracoes</h1>
        <p>Parametros operacionais, identidade visual e controles de seguranca do inventario.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-muted" href="/?route=audit.index"><?= icon('file-clock') ?><span>Auditoria</span></a>
        <a class="btn btn-primary" href="/"><?= icon('layout-dashboard') ?><span>Dashboard</span></a>
    </div>
</section>

<section class="audit-summary-grid settings-summary-grid">
    <article class="summary-card">
        <span class="summary-icon"><?= icon('settings') ?></span>
        <div>
            <strong><?= e(APP_NAME) ?></strong>
            <span>nome do sistema</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('check-circle') ?></span>
        <div>
            <strong>Ativa</strong>
            <span>auditoria operacional</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('history') ?></span>
        <div>
            <strong><?= e(PHP_VERSION) ?></strong>
            <span>versao PHP</span>
        </div>
    </article>
</section>

<section class="settings-grid">
    <article class="asset-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('settings') ?></span>
                <div>
                    <h2>Identidade da aplicacao</h2>
                    <p>Informacoes exibidas no painel administrativo.</p>
                </div>
            </div>
            <span class="status-chip neutral">Padrao</span>
        </header>
        <dl class="asset-property-grid">
            <div>
                <dt>Nome do sistema</dt>
                <dd><?= e(APP_NAME) ?></dd>
            </div>
            <div>
                <dt>Tema visual</dt>
                <dd>Claro e escuro</dd>
            </div>
            <div>
                <dt>Layout global</dt>
                <dd>Sidebar, topbar e navegacao mobile</dd>
            </div>
            <div>
                <dt>Interface</dt>
                <dd>EXE administrativo</dd>
            </div>
        </dl>
    </article>

    <article class="asset-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('file-clock') ?></span>
                <div>
                    <h2>Seguranca e auditoria</h2>
                    <p>Recursos ativos para rastreabilidade das acoes principais.</p>
                </div>
            </div>
            <a class="link-primary" href="/?route=audit.index">Ver logs</a>
        </header>
        <div class="settings-check-list">
            <div>
                <?= icon('check-circle') ?>
                <span>Logs do sistema ativos</span>
                <small>Login, empresas, dispositivos e fotos</small>
            </div>
            <div>
                <?= icon('check-circle') ?>
                <span>CSRF ativo em formularios</span>
                <small>Protecao em acoes de escrita</small>
            </div>
            <div>
                <?= icon('check-circle') ?>
                <span>Controle administrativo</span>
                <small>Usuarios comuns sem acesso a rotas restritas</small>
            </div>
        </div>
    </article>

    <article class="asset-panel settings-wide-panel">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('warning') ?></span>
                <div>
                    <h2>Parametros editaveis</h2>
                    <p>Area preparada para futuras preferencias persistentes.</p>
                </div>
            </div>
            <span class="status-chip neutral">Somente leitura</span>
        </header>
        <div class="settings-readonly">
            <p>Esta instalacao ainda nao possui backend para salvar configuracoes pela interface. A tela foi padronizada para exibir o estado atual do sistema com clareza e permitir evolucao futura sem quebrar o layout.</p>
        </div>
    </article>
</section>
