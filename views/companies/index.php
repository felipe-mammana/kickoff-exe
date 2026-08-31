<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span>Empresas</span>
</nav>

<section class="asset-page-head">
    <div>
        <h1>Empresas cadastradas</h1>
        <p>Gerencie organizações, padrões de etiqueta e inventários vinculados.</p>
    </div>
    <button class="btn btn-primary" type="button" data-company-modal-open><?= icon('plus') ?><span>Cadastrar nova empresa</span></button>
</section>

<section class="company-filter-panel">
    <div class="field">
        <span>Nome da empresa</span>
        <div class="input-with-icon">
            <?= icon('search') ?>
            <input type="search" placeholder="Ex.: Tech Solutions Ltda" data-company-search data-export-filter="name">
        </div>
    </div>
    <label class="field">
        <span>Status</span>
        <select data-company-status data-export-filter="status">
            <option value="">Todos os status</option>
            <option value="ativa">Ativa</option>
            <option value="inativa">Inativa</option>
        </select>
    </label>
    <button class="btn btn-muted" type="button"><?= icon('filter') ?><span>Filtrar</span></button>
</section>

<section class="content-panel company-table-panel">
    <div class="panel-header">
        <div>
            <span class="eyebrow">Cadastro</span>
            <h2>Empresas</h2>
            <p><?= count($companies) ?> registro(s)</p>
        </div>
        <div class="export-actions" data-export-actions>
            <a class="btn btn-muted export-btn <?= !$companies ? 'disabled' : '' ?>" href="<?= e(export_url('companies', 'csv')) ?>" data-export-link data-export-base="<?= e(export_url('companies', 'csv')) ?>" data-export-format="CSV" aria-disabled="<?= !$companies ? 'true' : 'false' ?>">
                <?= icon('file-spreadsheet') ?><span>Exportar CSV</span>
            </a>
            <a class="btn btn-muted export-btn <?= !$companies ? 'disabled' : '' ?>" href="<?= e(export_url('companies', 'json')) ?>" data-export-link data-export-base="<?= e(export_url('companies', 'json')) ?>" data-export-format="JSON" aria-disabled="<?= !$companies ? 'true' : 'false' ?>">
                <?= icon('braces') ?><span>Exportar JSON</span>
            </a>
        </div>
    </div>

    <?php if (!$companies): ?>
        <div class="empty-state compact">
            <h3>Nenhuma empresa cadastrada</h3>
            <p>Cadastre a primeira empresa para iniciar o inventario.</p>
            <button class="btn btn-primary" type="button" data-company-modal-open><?= icon('plus') ?><span>Cadastrar empresa</span></button>
        </div>
    <?php else: ?>
        <div class="inventory-table-wrap">
            <table class="inventory-table company-table">
                <thead>
                    <tr>
                        <th>Nome da empresa</th>
                        <th>Padrao etiqueta</th>
                        <th>Status</th>
                        <th>Cadastrada por</th>
                        <th>Última alteração</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company): ?>
                        <?php
                        $initials = strtoupper(substr((string) $company['name'], 0, 2));
                        $statusText = $company['is_active'] ? 'ativa' : 'inativa';
                        ?>
                        <tr data-company-row data-row-href="/?route=companies.show&id=<?= (int) $company['id'] ?>" tabindex="0" aria-label="Abrir empresa <?= e($company['name']) ?>" data-company-name="<?= e(strtolower((string) $company['name'])) ?>" data-company-status="<?= e($statusText) ?>">
                            <td data-label="Empresa">
                                <div class="company-cell">
                                    <span class="company-avatar"><?= e($initials) ?></span>
                                    <strong><?= e($company['name']) ?></strong>
                                </div>
                            </td>
                            <td data-label="Padrao">
                                <span class="tag-code"><?= e($company['tag_pattern'] ?: '-') ?></span>
                            </td>
                            <td data-label="Status">
                                <span class="status-badge <?= $company['is_active'] ? 'ok' : 'no' ?>">
                                    <?= $company['is_active'] ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </td>
                            <td data-label="Cadastrada por"><?= e($company['created_by_name'] ?: '-') ?></td>
                            <td data-label="Última alteração"><?= e($company['updated_at'] ?: '-') ?></td>
                            <td data-label="Ações">
                                <div class="table-actions">
                                    <a class="icon-btn" href="/?route=companies.show&id=<?= (int) $company['id'] ?>" aria-label="Ver empresa"><?= icon('eye') ?></a>
                                    <a class="icon-btn" href="/?route=companies.edit&id=<?= (int) $company['id'] ?>" aria-label="Editar empresa"><?= icon('edit-3') ?></a>
                                </div>
                                <small class="row-tap-hint"><?= icon('chevron-right') ?> Toque no card para ver detalhes</small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="company-modal" data-company-modal hidden>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="company-modal-title">
        <div class="gallery-head">
            <div>
                <span class="eyebrow">Nova empresa</span>
                <h2 id="company-modal-title">Cadastrar empresa</h2>
            </div>
            <button class="icon-btn" type="button" data-company-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </div>

        <form class="company-form modal-company-form" action="/?route=companies.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

            <div class="fields-grid">
                <label class="field">
                    <span>Nome da empresa</span>
                    <input type="text" name="name" placeholder="Ex.: Global Logistics S.A." required data-company-modal-focus>
                </label>

                <label class="field">
                    <span>Código da empresa para etiqueta</span>
                    <input type="text" name="tag_pattern" placeholder="EXE">
                </label>
            </div>

            <label class="check-card company-status-card">
                <input type="checkbox" name="is_active" checked>
                <span>Empresa ativa</span>
            </label>

            <div class="form-actions-bar company-actions">
                <button class="btn btn-muted" type="button" data-company-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Cadastrar empresa</span></button>
            </div>
        </form>
    </div>
</div>
