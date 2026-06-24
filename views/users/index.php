<?php
$adminCount = 0;
foreach ($users as $user) {
    if (!empty($user['is_admin'])) {
        $adminCount++;
    }
}
$standardCount = count($users) - $adminCount;
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <strong>Usuarios</strong>
</nav>

<section class="asset-page-head">
    <div>
        <span class="eyebrow">Administracao</span>
        <h1>Usuarios</h1>
        <p>Contas com acesso ao inventario, separadas por perfil administrativo e operacional.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-muted" href="/?route=audit.index"><?= icon('file-clock') ?><span>Ver logs</span></a>
        <a class="btn btn-primary" href="/"><?= icon('layout-dashboard') ?><span>Dashboard</span></a>
    </div>
</section>

<section class="audit-summary-grid user-summary-grid">
    <article class="summary-card">
        <span class="summary-icon"><?= icon('users') ?></span>
        <div>
            <strong><?= count($users) ?></strong>
            <span>contas cadastradas</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('settings') ?></span>
        <div>
            <strong><?= $adminCount ?></strong>
            <span>administradores</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('check-circle') ?></span>
        <div>
            <strong><?= $standardCount ?></strong>
            <span>usuarios padrao</span>
        </div>
    </article>
</section>

<section class="asset-panel user-table-panel">
    <header class="asset-panel-head">
        <div>
            <span><?= icon('users') ?></span>
            <div>
                <h2>Acessos cadastrados</h2>
                <p>Controle visual das contas habilitadas no sistema.</p>
            </div>
        </div>
        <div class="export-actions" data-export-actions>
            <span class="status-chip neutral"><?= count($users) ?> contas</span>
            <a class="btn btn-muted export-btn <?= !$users ? 'disabled' : '' ?>" href="<?= e(export_url('users', 'csv')) ?>" data-export-link data-export-format="CSV" aria-disabled="<?= !$users ? 'true' : 'false' ?>">
                <?= icon('file-spreadsheet') ?><span>Exportar CSV</span>
            </a>
            <a class="btn btn-muted export-btn <?= !$users ? 'disabled' : '' ?>" href="<?= e(export_url('users', 'json')) ?>" data-export-link data-export-format="JSON" aria-disabled="<?= !$users ? 'true' : 'false' ?>">
                <?= icon('braces') ?><span>Exportar JSON</span>
            </a>
        </div>
    </header>

    <?php if (!$users): ?>
        <div class="empty-state compact audit-empty">
            <span class="empty-icon"><?= icon('users') ?></span>
            <h3>Nenhum usuario cadastrado</h3>
            <p>Execute o seed administrativo ou cadastre uma conta antes de liberar o sistema.</p>
        </div>
    <?php else: ?>
        <div class="inventory-table-wrap">
            <table class="inventory-table user-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $name = (string) $user['name'];
                        $initial = strtoupper(substr(trim($name) !== '' ? trim($name) : 'U', 0, 1));
                        $isAdmin = !empty($user['is_admin']);
                        ?>
                        <tr>
                            <td data-label="Usuario">
                                <div class="audit-user-cell">
                                    <span class="audit-avatar"><?= e($initial) ?></span>
                                    <div>
                                        <strong><?= e($name) ?></strong>
                                        <small>ID #<?= (int) $user['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="E-mail">
                                <span class="user-email"><?= e($user['email']) ?></span>
                            </td>
                            <td data-label="Perfil">
                                <span class="audit-action-badge <?= $isAdmin ? 'info' : 'neutral' ?>">
                                    <?= icon($isAdmin ? 'settings' : 'users') ?>
                                    <?= $isAdmin ? 'Administrador' : 'Usuario padrao' ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="status-chip neutral"><?= icon('check-circle') ?><span>Ativo</span></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
