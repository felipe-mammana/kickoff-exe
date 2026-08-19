<?php
$actionLabels = [
    'company_created' => 'Empresa cadastrada',
    'company_updated' => 'Empresa editada',
    'company_deactivated' => 'Empresa desativada',
    'machine_created' => 'Dispositivo cadastrado',
    'machine_updated' => 'Dispositivo editado',
    'machine_deactivated' => 'Dispositivo desativado',
    'machine_photos_added' => 'Fotos adicionadas',
    'machine_photo_removed' => 'Foto removida',
    'login_success' => 'Login aprovado',
    'login_failed' => 'Falha de login',
    'logout' => 'Logout',
    'export_performed' => 'Exportacao realizada',
];

$actionClasses = [
    'login_failed' => 'danger',
    'machine_deactivated' => 'danger',
    'company_deactivated' => 'danger',
    'login_success' => 'success',
    'company_created' => 'success',
    'machine_created' => 'success',
    'company_updated' => 'info',
    'machine_updated' => 'info',
    'machine_photos_added' => 'info',
    'machine_photo_removed' => 'warning',
    'export_performed' => 'info',
    'logout' => 'muted',
];

$activeFilters = 0;
foreach ($filters as $filterValue) {
    if ((string) $filterValue !== '') {
        $activeFilters++;
    }
}
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <strong>Logs do sistema</strong>
</nav>

<section class="asset-page-head">
    <div>
        <span class="eyebrow">Auditoria</span>
        <h1>Logs do sistema</h1>
        <p>Historico centralizado de acessos, cadastros, edicoes e remocoes feitas no sistema EXE.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-muted" href="/?route=audit.index"><?= icon('history') ?><span>Atualizar</span></a>
        <a class="btn btn-primary" href="/"><?= icon('layout-dashboard') ?><span>Dashboard</span></a>
    </div>
</section>

<section class="audit-summary-grid">
    <article class="summary-card">
        <span class="summary-icon"><?= icon('file-clock') ?></span>
        <div>
            <strong><?= count($logs) ?></strong>
            <span>registros exibidos</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('filter') ?></span>
        <div>
            <strong><?= $activeFilters ?></strong>
            <span>filtros ativos</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('users') ?></span>
        <div>
            <strong><?= count($users) ?></strong>
            <span>usuarios monitorados</span>
        </div>
    </article>
</section>

<section class="asset-panel audit-filter-panel">
    <header class="asset-panel-head">
        <div>
            <span><?= icon('filter') ?></span>
            <div>
                <h2>Filtros de auditoria</h2>
                <p>Refine por usuario, empresa, tipo de acao ou periodo.</p>
            </div>
        </div>
        <?php if ($activeFilters > 0): ?>
            <a class="link-primary" href="/?route=audit.index">Limpar filtros</a>
        <?php endif; ?>
    </header>

    <form class="audit-filter-grid" method="get" action="/">
        <input type="hidden" name="route" value="audit.index">

        <label class="field">
            <span>Usuario</span>
            <select name="user_id">
                <option value="">Todos os usuarios</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= (string) $user['id'] === (string) $filters['user_id'] ? 'selected' : '' ?>>
                        <?= e($user['name']) ?> - <?= e($user['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Empresa</span>
            <select name="company_id">
                <option value="">Todas as empresas</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= (string) $company['id'] === (string) $filters['company_id'] ? 'selected' : '' ?>>
                        <?= e($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Tipo de acao</span>
            <select name="action_type">
                <option value="">Todas as acoes</option>
                <?php foreach ($actionTypes as $actionType): ?>
                    <option value="<?= e($actionType) ?>" <?= $actionType === $filters['action_type'] ? 'selected' : '' ?>>
                        <?= e($actionLabels[$actionType] ?? $actionType) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Data inicial</span>
            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
        </label>

        <label class="field">
            <span>Data final</span>
            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
        </label>

        <div class="filter-actions audit-filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('search') ?><span>Aplicar filtros</span></button>
            <a class="btn btn-muted" href="/?route=audit.index"><?= icon('history') ?><span>Limpar</span></a>
        </div>
    </form>
</section>

<section class="asset-panel audit-log-panel">
    <header class="asset-panel-head">
        <div>
            <span><?= icon('file-clock') ?></span>
            <div>
                <h2>Registros recentes</h2>
                <p>Ordem cronologica conforme retorno da auditoria.</p>
            </div>
        </div>
        <div class="export-actions" data-export-actions>
            <span class="status-chip neutral"><?= count($logs) ?> exibidos</span>
            <a class="btn btn-muted export-btn <?= !$logs ? 'disabled' : '' ?>" href="<?= e(export_url('audit', 'csv', $filters)) ?>" data-export-link data-export-format="CSV" aria-disabled="<?= !$logs ? 'true' : 'false' ?>">
                <?= icon('file-spreadsheet') ?><span>Exportar CSV</span>
            </a>
            <a class="btn btn-muted export-btn <?= !$logs ? 'disabled' : '' ?>" href="<?= e(export_url('audit', 'json', $filters)) ?>" data-export-link data-export-format="JSON" aria-disabled="<?= !$logs ? 'true' : 'false' ?>">
                <?= icon('braces') ?><span>Exportar JSON</span>
            </a>
        </div>
    </header>

    <?php if (!$logs): ?>
        <div class="empty-state compact audit-empty">
            <span class="empty-icon"><?= icon('file-clock') ?></span>
            <h3>Nenhum log encontrado</h3>
            <p>Ajuste os filtros ou execute novas acoes no sistema para alimentar a auditoria.</p>
        </div>
    <?php else: ?>
        <div class="inventory-table-wrap audit-table-wrap">
            <table class="inventory-table audit-table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Usuario</th>
                        <th>Origem</th>
                        <th>Registro relacionado</th>
                        <th>Dados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $actionType = (string) $log['action_type'];
                        $badgeClass = $actionClasses[$actionType] ?? 'neutral';
                        $userName = (string) ($log['user_name'] ?: 'Sem usuario');
                        $initial = strtoupper(substr(trim($userName) !== '' ? trim($userName) : 'S', 0, 1));
                        $hasChanges = (bool) ($log['old_data'] || $log['new_data']);
                        ?>
                        <tr>
                            <td data-label="Evento">
                                <div class="audit-event-cell">
                                    <span class="audit-action-badge <?= e($badgeClass) ?>">
                                        <?= icon($badgeClass === 'danger' ? 'warning' : 'check-circle') ?>
                                        <?= e($actionLabels[$actionType] ?? $actionType) ?>
                                    </span>
                                    <strong><?= e($log['description']) ?></strong>
                                    <small><?= e($log['created_at']) ?></small>
                                </div>
                            </td>
                            <td data-label="Usuario">
                                <div class="audit-user-cell">
                                    <span class="audit-avatar"><?= e($initial) ?></span>
                                    <div>
                                        <strong><?= e($userName) ?></strong>
                                        <small><?= e($log['user_email'] ?: '-') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Origem">
                                <div class="audit-muted-cell">
                                    <strong><?= e($log['ip_address'] ?: '-') ?></strong>
                                    <small>Endereco IP</small>
                                </div>
                            </td>
                            <td data-label="Registro relacionado">
                                <dl class="audit-mini-meta">
                                    <div>
                                        <dt>Empresa</dt>
                                        <dd><?= e($log['company_name'] ?: '-') ?></dd>
                                    </div>
                                </dl>
                            </td>
                            <td data-label="Dados">
                                <?php if ($hasChanges): ?>
                                    <button
                                        class="audit-change-trigger"
                                        type="button"
                                        data-audit-change-open
                                        data-audit-title="<?= e($actionLabels[$actionType] ?? $actionType) ?>"
                                        data-audit-description="<?= e($log['description']) ?>"
                                        data-audit-before="<?= e(pretty_json($log['old_data'])) ?>"
                                        data-audit-after="<?= e(pretty_json($log['new_data'])) ?>"
                                    >
                                        <?= icon('eye') ?><span>Ver alteracoes</span>
                                    </button>
                                <?php else: ?>
                                    <span class="status-chip neutral">Sem alteracoes</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="audit-change-modal" data-audit-change-modal hidden>
    <div class="audit-change-dialog" role="dialog" aria-modal="true" aria-labelledby="audit-change-title">
        <header class="audit-change-head">
            <div>
                <span class="eyebrow">Alteracoes registradas</span>
                <h2 id="audit-change-title" data-audit-change-title>Alteracoes</h2>
                <p data-audit-change-description></p>
            </div>
            <button class="icon-btn" type="button" data-audit-change-close aria-label="Fechar" title="Fechar"><?= icon('x') ?></button>
        </header>

        <div class="json-grid audit-json-grid audit-json-modal-grid">
            <div>
                <span>Antes</span>
                <pre data-audit-change-before>-</pre>
            </div>
            <div>
                <span>Depois</span>
                <pre data-audit-change-after>-</pre>
            </div>
        </div>
    </div>
</div>
