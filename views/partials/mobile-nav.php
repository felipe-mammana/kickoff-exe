<nav class="mobile-nav" aria-label="Menu mobile">
    <a class="<?= $route === 'dashboard' ? 'active' : '' ?>" href="/">
        <?= icon('layout-dashboard') ?>
        <span>Home</span>
    </a>
    <?php if (can_permission('machines.create') && $companyIdForNav && (empty($company) || !isset($company['is_active']) || !empty($company['is_active']))): ?>
        <a class="mobile-primary <?= $route === 'machines.create' ? 'active' : '' ?>" href="/?route=machines.create&company_id=<?= (int) $companyIdForNav ?>">
            <?= icon('plus') ?>
            <span>Add</span>
        </a>
    <?php endif; ?>
    <?php if (can_access_vault()): ?>
    <a class="<?= $isVault ? 'active' : '' ?>" href="/?route=vault.index">
        <?= icon('lock') ?>
        <span>Cofre</span>
    </a>
    <?php endif; ?>
    <?php if (can_permission('audit.view')): ?>
        <a class="<?= $isAudit ? 'active' : '' ?>" href="/?route=audit.index">
            <?= icon('file-clock') ?>
            <span>Logs</span>
        </a>
    <?php endif; ?>
    <a class="<?= $isSettings ? 'active' : '' ?>" href="/?route=settings.index">
        <?= icon('settings') ?>
        <span>Settings</span>
    </a>
</nav>
