<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span>Cofre</span>
</nav>

<section class="asset-page-head">
    <div>
        <h1>Cofre de senhas</h1>
        <p>Área separada para organizar credenciais das empresas com controle de acesso e auditoria.</p>
    </div>
    <button class="btn btn-primary" type="button" disabled title="Sera habilitado na proxima fase">
        <?= icon('plus') ?><span>Nova credencial</span>
    </button>
</section>

<?php $isCredentialMode = ($filters['search_mode'] ?? 'company') === 'credential'; ?>

<section class="content-panel">
    <div class="panel-header">
        <div>
            <span class="eyebrow">Filtros</span>
            <h2>Consultar cofre</h2>
            <p>Escolha se deseja procurar empresas ou credenciais.</p>
        </div>
    </div>

    <form class="filters-grid vault-filters" method="get" action="/">
        <input type="hidden" name="route" value="vault.index">
        <label class="field">
            <span>Buscar por</span>
            <select name="search_mode">
                <option value="company" <?= !$isCredentialMode ? 'selected' : '' ?>>Empresa</option>
                <option value="credential" <?= $isCredentialMode ? 'selected' : '' ?>>Nome da credencial</option>
            </select>
        </label>
        <label class="field">
            <span>Pesquisa</span>
            <input
                type="search"
                name="query"
                value="<?= e($filters['query']) ?>"
                placeholder="<?= $isCredentialMode ? 'Digite o nome da credencial' : 'Digite o nome da empresa' ?>"
            >
        </label>
        <div class="filter-actions vault-filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('filter') ?><span>Filtrar</span></button>
            <a class="btn btn-muted" href="/?route=vault.index">Limpar</a>
        </div>
    </form>
</section>

<section class="content-panel vault-main-panel">
    <div class="panel-header">
        <div>
            <span class="eyebrow"><?= $isCredentialMode ? 'Credenciais' : 'Empresas' ?></span>
            <h2><?= $isCredentialMode ? 'Credenciais encontradas' : 'Empresas encontradas' ?></h2>
            <p><?= $isCredentialMode ? count($credentials) . ' credencial(is) encontrada(s)' : count($companies) . ' empresa(s) encontrada(s)' ?></p>
        </div>
    </div>

    <?php if ($isCredentialMode && !$credentials): ?>
        <div class="empty-state compact">
            <h3>Nenhum resultado encontrado</h3>
            <p>Altere o nome pesquisado ou cadastre uma nova credencial na empresa correta.</p>
        </div>
    <?php elseif (!$isCredentialMode && !$companies): ?>
        <div class="empty-state compact">
            <h3>Nenhuma empresa encontrada</h3>
            <p>Altere o nome pesquisado ou cadastre a empresa antes de adicionar credenciais.</p>
        </div>
    <?php else: ?>
        <div class="inventory-table-wrap">
            <?php if ($isCredentialMode): ?>
                <table class="inventory-table vault-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Empresa</th>
                            <th>Tipo</th>
                            <th>Usuário</th>
                            <th>URL</th>
                            <th>Atualização</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($credentials as $credential): ?>
                            <?php
                            $credentialUrl = '/?route=vault.show&id=' . (int) $credential['company_id'];
                            if (!empty($credential['category_id'])) {
                                $credentialUrl .= '&category_id=' . (int) $credential['category_id'];
                            }
                            ?>
                            <tr>
                                <td data-label="Nome">
                                    <a class="vault-inline-link truncate-text" href="<?= e($credentialUrl) ?>" title="<?= e($credential['title']) ?>">
                                        <?= e($credential['title']) ?>
                                    </a>
                                </td>
                                <td data-label="Empresa">
                                    <a class="vault-company-cell" href="/?route=vault.show&id=<?= (int) $credential['company_id'] ?>">
                                        <span class="avatar compact"><?= e(strtoupper(substr((string) $credential['company_name'], 0, 1))) ?></span>
                                        <strong class="truncate-text" title="<?= e($credential['company_name']) ?>"><?= e($credential['company_name']) ?></strong>
                                    </a>
                                </td>
                                <td data-label="Tipo">
                                    <span class="vault-type-chip" title="<?= e($credential['category_name'] ?: 'Sem tipo') ?>">
                                        <?= icon($credential['category_icon'] ?: 'lock') ?>
                                        <span><?= e($credential['category_name'] ?: 'Sem tipo') ?></span>
                                    </span>
                                </td>
                                <td data-label="Usuário">
                                    <span class="truncate-text" title="<?= e($credential['username'] ?: '-') ?>"><?= e($credential['username'] ?: '-') ?></span>
                                </td>
                                <td data-label="URL">
                                    <?php $safeUrl = safe_external_url($credential['service_url'] ?? null); ?>
                                    <?php if ($safeUrl !== null): ?>
                                        <a class="vault-inline-link truncate-text" href="<?= e($safeUrl) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($credential['service_url']) ?>">
                                            <?= e($credential['service_url']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="truncate-text" title="<?= e($credential['service_url'] ?: '-') ?>"><?= e($credential['service_url'] ?: '-') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Atualização"><?= e($credential['updated_at'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="inventory-table vault-table">
                    <thead>
                        <tr>
                            <th>Empresa</th>
                            <th>Credenciais</th>
                            <th>Última atualização</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                        <tr>
                            <td data-label="Empresa">
                                <a class="vault-company-cell" href="/?route=vault.show&id=<?= (int) $company['id'] ?>">
                                    <span class="avatar compact"><?= e(strtoupper(substr((string) $company['name'], 0, 1))) ?></span>
                                    <strong><?= e($company['name']) ?></strong>
                                </a>
                            </td>
                            <td data-label="Credenciais">
                                <span class="status-chip <?= (int) $company['credentials_count'] > 0 ? 'success' : 'neutral' ?>">
                                    <?= (int) $company['credentials_count'] ?>
                                </span>
                            </td>
                            <td data-label="Última atualização"><?= e($company['last_updated_at'] ?: '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
