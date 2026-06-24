<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?route=companies.index">Empresas</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span><?= e($company['name']) ?></span>
</nav>

<section class="device-hero">
    <div>
        <div class="device-kicker">
            <span class="asset-type"><?= icon('building-2') ?>Empresa</span>
            <span class="status-badge <?= $company['is_active'] ? 'ok' : 'no' ?>"><?= $company['is_active'] ? 'Ativa' : 'Inativa' ?></span>
        </div>
        <h1><?= e($company['name']) ?></h1>
        <p>Padrao de etiqueta: <span class="mono"><?= e($company['tag_pattern'] ?: '-') ?></span></p>
    </div>
    <div class="heading-actions">
        <a class="btn btn-muted" href="/?route=companies.index"><?= icon('chevron-left') ?><span>Voltar</span></a>
        <a class="btn btn-primary" href="/?route=companies.edit&id=<?= (int) $company['id'] ?>"><?= icon('edit-3') ?><span>Editar</span></a>
        <?php if (!empty($company['is_active'])): ?>
            <form action="/?route=companies.deactivate&id=<?= (int) $company['id'] ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-danger" type="submit"><?= icon('trash-2') ?><span>Desativar</span></button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="device-summary-grid">
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon('building-2') ?></span>
        <div>
            <small>Empresa</small>
            <strong><?= e($company['name']) ?></strong>
        </div>
    </article>
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon('tag') ?></span>
        <div>
            <small>Padrao etiqueta</small>
            <strong class="mono"><?= e($company['tag_pattern'] ?: '-') ?></strong>
        </div>
    </article>
    <article class="summary-chip-card">
        <span class="metric-icon <?= $company['is_active'] ? 'success' : 'warning' ?>"><?= icon($company['is_active'] ? 'check-circle' : 'warning') ?></span>
        <div>
            <small>Status</small>
            <strong><?= $company['is_active'] ? 'Ativa' : 'Inativa' ?></strong>
        </div>
    </article>
</section>

<section class="device-detail-grid">
    <div class="device-main-column">
        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('file-text') ?>
                    <h2>Dados da empresa</h2>
                </div>
            </header>

            <dl class="asset-property-grid">
                <div><dt>Nome</dt><dd><?= e($company['name']) ?></dd></div>
                <div><dt>Padrao de etiqueta</dt><dd class="mono"><?= e($company['tag_pattern'] ?: '-') ?></dd></div>
                <div><dt>Status</dt><dd><?= $company['is_active'] ? 'Ativa' : 'Inativa' ?></dd></div>
                <div><dt>Data de cadastro</dt><dd><?= e($company['created_at'] ?: '-') ?></dd></div>
                <div><dt>Usuario que cadastrou</dt><dd><?= e($company['created_by_name'] ?: '-') ?></dd></div>
                <div><dt>Ultima alteracao</dt><dd><?= e($company['updated_at'] ?: '-') ?></dd></div>
                <div><dt>Usuario que alterou</dt><dd><?= e($company['updated_by_name'] ?: '-') ?></dd></div>
            </dl>
        </article>
    </div>

    <aside class="device-side-column">
        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('monitor-cog') ?>
                    <h2>Inventario</h2>
                </div>
            </header>
            <div class="quick-panel-body">
                <p>Veja os dispositivos vinculados a esta empresa e cadastre novos ativos usando o padrao definido.</p>
                <a class="btn btn-primary btn-full" href="/?company_id=<?= (int) $company['id'] ?>"><?= icon('monitor-cog') ?><span>Ver dispositivos</span></a>
            </div>
        </article>

        <?php if (!empty($company['is_active'])): ?>
            <article class="danger-panel">
                <h2><?= icon('warning') ?> Zona de risco</h2>
                <p>A desativacao remove a empresa dos fluxos principais sem apagar seu historico.</p>
                <form action="/?route=companies.deactivate&id=<?= (int) $company['id'] ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-danger btn-full" type="submit"><?= icon('trash-2') ?><span>Desativar empresa</span></button>
                </form>
            </article>
        <?php endif; ?>

        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('history') ?>
                    <h2>Historico da empresa</h2>
                </div>
                <span><?= count($history) ?> evento(s)</span>
            </header>

            <?php if (!$history): ?>
                <div class="empty-state compact">
                    <h3>Nenhum historico encontrado</h3>
                    <p>As proximas alteracoes desta empresa aparecerao aqui.</p>
                </div>
            <?php else: ?>
                <div class="asset-timeline">
                    <?php foreach ($history as $event): ?>
                        <article>
                            <span></span>
                            <div>
                                <strong><?= e($event['description']) ?></strong>
                                <small><?= e($event['created_at']) ?> · <?= e($event['user_name'] ?: 'Sem usuario') ?></small>
                                <?php if ($event['old_data'] || $event['new_data']): ?>
                                    <details>
                                        <summary>Ver alteracoes</summary>
                                        <div class="json-grid">
                                            <pre><?= e(pretty_json($event['old_data'])) ?></pre>
                                            <pre><?= e(pretty_json($event['new_data'])) ?></pre>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </aside>
</section>
