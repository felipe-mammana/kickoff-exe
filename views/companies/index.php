<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span>Empresas</span>
</nav>

<section class="asset-page-head">
    <div>
        <h1>Empresas cadastradas</h1>
        <p>Gerencie organizacoes, padroes de etiqueta e inventarios vinculados.</p>
    </div>
    <a class="btn btn-primary" href="/?route=companies.create"><?= icon('plus') ?><span>Cadastrar nova empresa</span></a>
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
            <a class="btn btn-primary" href="/?route=companies.create"><?= icon('plus') ?><span>Cadastrar empresa</span></a>
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
                        <th>Ultima alteracao</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($companies as $company): ?>
                        <?php
                        $initials = strtoupper(substr((string) $company['name'], 0, 2));
                        $statusText = $company['is_active'] ? 'ativa' : 'inativa';
                        ?>
                        <tr data-company-row data-company-name="<?= e(strtolower((string) $company['name'])) ?>" data-company-status="<?= e($statusText) ?>">
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
                            <td data-label="Ultima alteracao"><?= e($company['updated_at'] ?: '-') ?></td>
                            <td data-label="Acoes">
                                <div class="table-actions">
                                    <a class="icon-btn" href="/?route=companies.show&id=<?= (int) $company['id'] ?>" aria-label="Ver empresa"><?= icon('eye') ?></a>
                                    <a class="icon-btn" href="/?route=companies.edit&id=<?= (int) $company['id'] ?>" aria-label="Editar empresa"><?= icon('edit-3') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
