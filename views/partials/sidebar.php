<aside class="sidebar" data-sidebar>
    <div class="sidebar-head">
        <a class="brand sidebar-brand" href="/">
            <?= brand_logo('brand-logo sidebar-logo-full') ?>
            <?= brand_logo('brand-icon sidebar-logo-icon', true) ?>
        </a>
        <button class="icon-btn sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu" title="Fechar menu">
            <?= icon('x') ?>
        </button>
    </div>

    <nav class="side-nav" aria-label="Menu principal">
        <a class="<?= $route === 'dashboard' ? 'active' : '' ?>" href="/">
            <span class="nav-icon"><?= icon('layout-dashboard') ?></span>
            <span>Dashboard</span>
        </a>
        <?php if (is_admin()): ?>
            <a class="<?= $isCompany ? 'active' : '' ?>" href="/?route=companies.index">
                <span class="nav-icon"><?= icon('building-2') ?></span>
                <span>Empresas</span>
            </a>
            <a class="<?= $isAudit ? 'active' : '' ?>" href="/?route=audit.index">
                <span class="nav-icon"><?= icon('file-clock') ?></span>
                <span>Logs do sistema</span>
            </a>
            <a class="<?= $isUsers ? 'active' : '' ?>" href="/?route=users.index">
                <span class="nav-icon"><?= icon('users') ?></span>
                <span>Usuários</span>
            </a>
            <a class="<?= $isVault ? 'active' : '' ?>" href="/?route=vault.index">
                <span class="nav-icon"><?= icon('lock') ?></span>
                <span>Cofre</span>
            </a>
            <a class="<?= $isSettings ? 'active' : '' ?>" href="/?route=settings.index">
                <span class="nav-icon"><?= icon('settings') ?></span>
                <span>Configurações</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-profile">
        <div class="avatar"><?= e(strtoupper(substr((string) current_user()['name'], 0, 1))) ?></div>
        <div>
            <strong><?= e(current_user()['name']) ?></strong>
            <small><?= is_admin() ? 'Administrador' : 'Usuário' ?></small>
        </div>
    </div>
</aside>
