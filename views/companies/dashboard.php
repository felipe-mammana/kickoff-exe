<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span>Dashboard</span>
</nav>

<section class="asset-page-head">
    <div>
        <h1>Dashboard Geral</h1>
        <p>Visao consolidada do parque tecnologico e dos dispositivos cadastrados.</p>
    </div>

    <form class="asset-company-select" method="get" action="/">
        <input type="hidden" name="route" value="dashboard">
        <label class="field">
            <span>Selecionar empresa</span>
            <select name="company_id" onchange="this.form.submit()">
                <?php foreach ($companies as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= $company && (int) $company['id'] === (int) $item['id'] ? 'selected' : '' ?>>
                        <?= e($item['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</section>

<?php if (!$company): ?>
    <section class="empty-state">
        <h2>Nenhuma empresa cadastrada</h2>
        <p>Cadastre uma empresa para iniciar o inventario.</p>
        <?php if (is_admin()): ?>
            <a class="btn btn-primary" href="/?route=companies.create"><?= icon('plus') ?><span>Cadastrar empresa</span></a>
        <?php endif; ?>
    </section>
<?php else: ?>
    <?php
    $machineTitle = static function (array $item): string {
        return (string) (($item['tag'] ?? '') ?: ($item['new_hostname'] ?? '') ?: ($item['old_hostname'] ?? '') ?: 'Dispositivo');
    };
    $machineModel = static function (array $item): string {
        return (string) (($item['brand'] ?? '') ?: ($item['printer_brand'] ?? '') ?: ($item['computer_model'] ?? '') ?: '-');
    };
    ?>

    <section class="asset-metrics" aria-label="Resumo da empresa">
        <article class="asset-metric-card">
            <div class="metric-icon"><?= icon('building-2') ?></div>
            <span>Total de dispositivos</span>
            <strong><?= (int) $stats['total'] ?></strong>
            <small><?= e($company['name']) ?></small>
        </article>
        <article class="asset-metric-card">
            <div class="metric-icon success"><?= icon('laptop') ?></div>
            <span>Notebooks</span>
            <strong><?= (int) ($stats['notebooks'] ?? 0) ?></strong>
            <small>Ativos no inventario</small>
        </article>
        <article class="asset-metric-card">
            <div class="metric-icon success"><?= icon('monitor-cog') ?></div>
            <span>CPU / Desktop</span>
            <strong><?= (int) ($stats['cpus'] ?? 0) ?></strong>
            <small>Esta empresa</small>
        </article>
        <article class="asset-metric-card">
            <div class="metric-icon warning"><?= icon('printer') ?></div>
            <span>Impressoras</span>
            <strong><?= (int) ($stats['printers'] ?? 0) ?></strong>
            <small>Com ou sem rede</small>
        </article>
    </section>

    <section class="dashboard-grid">
        <section class="content-panel dashboard-main">
            <div class="panel-header">
                <div>
                    <span class="eyebrow">Inventario</span>
                    <h2>Dispositivos cadastrados</h2>
                    <p><?= count($machines) ?> registro(s) encontrados</p>
                </div>
                <div class="panel-actions">
                    <a class="icon-btn primary-action" href="/?route=machines.create&company_id=<?= (int) $company['id'] ?>" aria-label="Cadastrar dispositivo" title="Cadastrar dispositivo"><?= icon('plus') ?></a>
                    <a class="icon-btn export-btn <?= !$machines ? 'disabled' : '' ?>" href="<?= e(export_url('devices', 'csv', array_merge(['company_id' => (int) $company['id']], $filters))) ?>" data-export-link data-export-format="CSV" aria-label="Exportar CSV" title="Exportar CSV" aria-disabled="<?= !$machines ? 'true' : 'false' ?>">
                        <?= icon('file-spreadsheet') ?>
                    </a>
                    <a class="icon-btn export-btn <?= !$machines ? 'disabled' : '' ?>" href="<?= e(export_url('devices', 'json', array_merge(['company_id' => (int) $company['id']], $filters))) ?>" data-export-link data-export-format="JSON" aria-label="Exportar JSON" title="Exportar JSON" aria-disabled="<?= !$machines ? 'true' : 'false' ?>">
                        <?= icon('braces') ?>
                    </a>
                </div>
            </div>

            <details class="filter-drawer">
                <summary><?= icon('filter') ?><span>Filtros avancados</span></summary>
                <form class="filters-grid" method="get" action="/">
                    <input type="hidden" name="route" value="dashboard">
                    <label class="field">
                        <span>Empresa</span>
                        <select name="company_id">
                            <?php foreach ($companies as $item): ?>
                                <option value="<?= (int) $item['id'] ?>" <?= $company && (int) $company['id'] === (int) $item['id'] ? 'selected' : '' ?>>
                                    <?= e($item['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Tipo</span>
                        <select name="device_type">
                            <option value="">Todos</option>
                            <?php foreach ($deviceTypes as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= ($filters['device_type'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Status</span>
                        <select name="status">
                            <option value="active" <?= ($filters['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Ativos</option>
                            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                            <option value="all" <?= ($filters['status'] ?? '') === 'all' ? 'selected' : '' ?>>Todos</option>
                        </select>
                    </label>
                    <label class="field">
                        <span>Etiqueta</span>
                        <input type="text" name="tag" value="<?= e($filters['tag'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Responsavel</span>
                        <input type="text" name="employee_name" value="<?= e($filters['employee_name'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Departamento</span>
                        <input type="text" name="department" value="<?= e($filters['department'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Modelo</span>
                        <input type="text" name="computer_model" value="<?= e($filters['computer_model'] ?? '') ?>">
                    </label>
                    <label class="field">
                        <span>Data de cadastro</span>
                        <input type="date" name="created_at" value="<?= e($filters['created_at'] ?? '') ?>">
                    </label>
                    <div class="filter-actions">
                        <button class="btn btn-primary" type="submit"><?= icon('filter') ?><span>Filtrar</span></button>
                        <a class="btn btn-muted" href="/?company_id=<?= (int) $company['id'] ?>">Limpar</a>
                    </div>
                </form>
            </details>

            <?php if (!$machines): ?>
                <div class="empty-state compact">
                    <h3>Nenhum dispositivo encontrado</h3>
                    <p>Adicione o primeiro equipamento desta empresa.</p>
                    <a class="btn btn-primary" href="/?route=machines.create&company_id=<?= (int) $company['id'] ?>"><?= icon('plus') ?><span>Cadastrar dispositivo</span></a>
                </div>
            <?php else: ?>
                <div class="inventory-table-wrap">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Etiqueta</th>
                                <th>Hostname</th>
                                <th>Colaborador</th>
                                <th>Modelo</th>
                                <th>Checklist</th>
                                <th>Status</th>
                                <th>Fotos</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($machines as $machine): ?>
                                <?php
                                $machinePhotos = $photosByMachine[(int) $machine['id']] ?? [];
                                $typeIcon = [
                                    'notebook' => 'laptop',
                                    'cpu' => 'monitor-cog',
                                    'roteador' => 'router',
                                    'access_point' => 'router',
                                    'modem' => 'router',
                                    'impressora' => 'printer',
                                ][$machine['device_type'] ?? ''] ?? 'settings';
                                ?>
                                <tr>
                                    <td data-label="Tipo">
                                        <span class="asset-type">
                                            <?= icon($typeIcon) ?>
                                            <?= e($deviceTypes[$machine['device_type'] ?? ''] ?? 'Dispositivo') ?>
                                        </span>
                                    </td>
                                    <td data-label="Etiqueta">
                                        <strong class="mono"><?= e(($machine['tag'] ?? '') ?: '-') ?></strong>
                                        <small>Atualizado em <?= e(($machine['updated_at'] ?? '') ?: '-') ?></small>
                                    </td>
                                    <td data-label="Hostname">
                                        <?php if (in_array($machine['device_type'] ?? '', ['notebook', 'cpu'], true)): ?>
                                            <?= e(($machine['new_hostname'] ?? '') ?: '-') ?>
                                            <small>Antigo: <?= e(($machine['old_hostname'] ?? '') ?: '-') ?></small>
                                        <?php elseif (($machine['device_type'] ?? '') === 'access_point'): ?>
                                            <?= e(($machine['install_location'] ?? '') ?: '-') ?>
                                        <?php elseif (($machine['device_type'] ?? '') === 'modem'): ?>
                                            <?= e(($machine['carrier'] ?? '') ?: ($machine['modem_name'] ?? '') ?: '-') ?>
                                        <?php elseif (($machine['device_type'] ?? '') === 'roteador'): ?>
                                            <?= e(($machine['ip_address'] ?? '') ?: '-') ?>
                                        <?php else: ?>
                                            <?= e(($machine['computer_model'] ?? '') ?: '-') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Colaborador">
                                        <?= e(($machine['employee_name'] ?? '') ?: ($machine['install_location'] ?? '') ?: ($machine['admin_user'] ?? '') ?: '-') ?>
                                        <?php if (!empty($machine['department'])): ?><small><?= e($machine['department']) ?></small><?php endif; ?>
                                    </td>
                                    <td data-label="Modelo"><?= e($machineModel($machine)) ?></td>
                                    <td data-label="Checklist">
                                        <?php if (in_array($machine['device_type'] ?? '', ['notebook', 'cpu'], true)): ?>
                                            <div class="mini-status-list">
                                                <span class="status-chip <?= !empty($machine['tflux_installed']) ? 'success' : 'neutral' ?>">TFlux: <?= !empty($machine['tflux_installed']) ? 'Sim' : 'Nao' ?></span>
                                                <span class="status-chip <?= !empty($machine['antivirus_installed']) ? 'success' : 'neutral' ?>">AV: <?= !empty($machine['antivirus_installed']) ? 'Sim' : 'Nao' ?></span>
                                                <span class="status-chip <?= !empty($machine['requester_in_tflux']) ? 'success' : 'neutral' ?>">Solic.: <?= !empty($machine['requester_in_tflux']) ? 'Sim' : 'Nao' ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="status-chip neutral">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Status"><span class="status-badge <?= !empty($machine['is_active']) ? 'ok' : 'no' ?>"><?= !empty($machine['is_active']) ? 'Ativo' : 'Inativo' ?></span></td>
                                    <td data-label="Fotos">
                                        <?php if ($machinePhotos): ?>
                                            <button class="photo-gallery-trigger" type="button" data-gallery-open="<?= (int) $machine['id'] ?>" aria-label="Abrir galeria de fotos de <?= e(($machine['tag'] ?? '') ?: 'dispositivo') ?>">
                                                <?= icon('camera') ?>
                                                <strong><?= count($machinePhotos) ?></strong>
                                            </button>
                                        <?php else: ?>
                                            <span class="photo-empty">Sem fotos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Acoes">
                                        <div class="table-actions">
                                            <a class="icon-btn" href="/?route=machines.show&id=<?= (int) $machine['id'] ?>" aria-label="Ver"><?= icon('eye') ?></a>
                                            <a class="icon-btn" href="/?route=machines.edit&id=<?= (int) $machine['id'] ?>" aria-label="Editar"><?= icon('edit-3') ?></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <article class="content-panel dashboard-activity">
            <div class="panel-header compact">
                <div>
                    <span class="eyebrow">Atividade</span>
                    <h2>Ultimas alteracoes</h2>
                </div>
                <a class="icon-btn" href="/?route=audit.index" aria-label="Ver logs" title="Ver logs"><?= icon('file-clock') ?></a>
            </div>

            <?php if (!$machines): ?>
                <div class="empty-state compact">
                    <h3>Sem atividade recente</h3>
                    <p>Novos cadastros aparecerao aqui.</p>
                </div>
            <?php else: ?>
                <ol class="activity-list">
                    <?php foreach (array_slice($machines, 0, 4) as $item): ?>
                        <li>
                            <span class="activity-dot"></span>
                            <div>
                                <strong><?= e($machineTitle($item)) ?></strong>
                                <small><?= e($deviceTypes[$item['device_type'] ?? ''] ?? 'Dispositivo') ?> - <?= e(($item['updated_at'] ?? '') ?: ($item['created_at'] ?? '')) ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </article>
    </section>

    <div class="gallery-modal" data-gallery-modal hidden>
        <div class="gallery-dialog" role="dialog" aria-modal="true" aria-label="Galeria de fotos">
            <div class="gallery-head">
                <div>
                    <span class="eyebrow">Fotos cadastradas</span>
                    <h2 data-gallery-title>Galeria</h2>
                </div>
                <button class="btn btn-muted" type="button" data-gallery-close>Fechar</button>
            </div>

            <?php foreach ($machines as $machine): ?>
                <?php $machinePhotos = $photosByMachine[(int) $machine['id']] ?? []; ?>
                <div class="gallery-set" data-gallery-set="<?= (int) $machine['id'] ?>" hidden>
                    <?php if ($machinePhotos): ?>
                        <div class="photo-grid">
                            <?php foreach ($machinePhotos as $photo): ?>
                                <figure>
                                    <button class="photo-button" type="button" data-lightbox-src="<?= e(UPLOAD_URL . '/' . ($photo['file_name'] ?? '')) ?>" data-lightbox-alt="<?= e($photo['original_name'] ?? 'Foto do dispositivo') ?>">
                                        <img src="<?= e(UPLOAD_URL . '/' . ($photo['file_name'] ?? '')) ?>" alt="<?= e($photo['original_name'] ?? 'Foto do dispositivo') ?>">
                                    </button>
                                    <figcaption><?= e($photo['original_name'] ?? 'Foto do dispositivo') ?></figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state compact">
                            <h3>Nenhuma foto cadastrada</h3>
                            <p>Edite o dispositivo para adicionar fotos.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="lightbox" data-lightbox hidden>
        <div class="lightbox-topbar">
            <div>
                <strong data-lightbox-title>Foto do dispositivo</strong>
                <span data-lightbox-meta></span>
            </div>
            <div class="lightbox-actions">
                <a class="lightbox-action" href="#" download data-lightbox-download><?= icon('download') ?><span>Download</span></a>
                <button class="lightbox-close" type="button" data-lightbox-close aria-label="Fechar"><?= icon('chevron-right') ?></button>
            </div>
        </div>
        <button class="lightbox-nav prev" type="button" data-lightbox-prev aria-label="Foto anterior"><?= icon('chevron-left') ?></button>
        <figure class="lightbox-stage">
            <img src="" alt="" data-lightbox-img>
        </figure>
        <button class="lightbox-nav next" type="button" data-lightbox-next aria-label="Proxima foto"><?= icon('chevron-right') ?></button>
        <div class="lightbox-thumbs" data-lightbox-thumbs></div>
    </div>
<?php endif; ?>
