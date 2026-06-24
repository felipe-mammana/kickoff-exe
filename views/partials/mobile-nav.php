<nav class="mobile-nav" aria-label="Menu mobile">
    <a class="<?= $route === 'dashboard' ? 'active' : '' ?>" href="/">
        <?= icon('layout-dashboard') ?>
        <span>Home</span>
    </a>
    <?php if ($companyIdForNav): ?>
        <a class="mobile-primary <?= $route === 'machines.create' ? 'active' : '' ?>" href="/?route=machines.create&company_id=<?= (int) $companyIdForNav ?>">
            <?= icon('plus') ?>
            <span>Add</span>
        </a>
    <?php endif; ?>
    <?php if (is_admin()): ?>
        <a class="<?= $isAudit ? 'active' : '' ?>" href="/?route=audit.index">
            <?= icon('file-clock') ?>
            <span>Logs</span>
        </a>
        <a class="<?= $isSettings ? 'active' : '' ?>" href="/?route=settings.index">
            <?= icon('settings') ?>
            <span>Settings</span>
        </a>
    <?php endif; ?>
</nav>
