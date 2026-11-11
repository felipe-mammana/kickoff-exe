<?php
$formErrors = $_SESSION['vault_form_errors'] ?? [];
$formOld = $_SESSION['vault_form_old'] ?? [];
$openModal = (string) ($_GET['open_modal'] ?? '');
unset($_SESSION['vault_form_errors'], $_SESSION['vault_form_old']);
$accessibleCompanies = $accessibleCompanies ?? [];
$credentialCategories = $credentialCategories ?? [];
$vaultCustomFieldConfig = $vaultCustomFieldConfig ?? [];
$selectedCompanyId = (int) ($formOld['company_id'] ?? 0);
$selectedCategoryId = (int) ($formOld['category_id'] ?? 0);
$categoryName = '';
foreach ($credentialCategories as $category) {
    if ((int) $category['id'] === $selectedCategoryId) {
        $categoryName = !empty($category['parent_name']) ? $category['parent_name'] . ' / ' . $category['name'] : $category['name'];
        break;
    }
}
?>
<script type="application/json" data-vault-custom-field-config><?= e(json_encode($vaultCustomFieldConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></script>

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
    <?php if (can_permission('vault.create')): ?>
        <button class="btn btn-primary" type="button" data-vault-modal-open="create" <?= !$accessibleCompanies ? 'disabled title="Nenhuma empresa ativa disponível"' : '' ?>>
            <?= icon('plus') ?><span>Nova credencial</span>
        </button>
    <?php endif; ?>
</section>

<?php if (can_permission('vault.create')): ?>
<div class="company-modal vault-modal" data-vault-modal="create" <?= $openModal === 'create' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog vault-credential-dialog" role="dialog" aria-modal="true" aria-labelledby="vault-index-create-title">
        <header class="modal-head">
            <div><span class="eyebrow">Cofre</span><h2 id="vault-index-create-title">Nova credencial</h2></div>
            <button class="icon-btn" type="button" data-vault-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form vault-credential-form vault-credential-grid" action="/?route=vault.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="form_context" value="index">
            <label class="field <?= isset($formErrors['company_id']) ? 'has-error' : '' ?>">
                <span>Empresa</span>
                <select name="company_id" required data-vault-create-company data-vault-modal-focus>
                    <option value="">Selecione uma empresa</option>
                    <?php foreach ($accessibleCompanies as $accessibleCompany): ?>
                        <option value="<?= (int) $accessibleCompany['id'] ?>" <?= $selectedCompanyId === (int) $accessibleCompany['id'] ? 'selected' : '' ?>><?= e($accessibleCompany['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['company_id'])): ?><small><?= e($formErrors['company_id']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['title']) ? 'has-error' : '' ?>">
                <span>Nome</span><input type="text" name="title" value="<?= e($formOld['title'] ?? '') ?>" maxlength="160" required>
                <?php if (isset($formErrors['title'])): ?><small><?= e($formErrors['title']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['category_id']) ? 'has-error' : '' ?>">
                <span>Categoria</span>
                <div class="vault-category-combobox" data-vault-category-combobox>
                    <input type="text" value="<?= e($categoryName) ?>" placeholder="Selecione ou busque uma categoria" autocomplete="off" data-vault-category-search-input>
                    <select class="vault-native-category-select" name="category_id" required data-vault-create-category tabindex="-1" aria-hidden="true">
                        <option value="">Selecione uma categoria</option>
                        <?php foreach ($credentialCategories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" data-company-ids="<?= e((string) ($category['allowed_company_ids'] ?? '')) ?>" <?= $selectedCategoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e(!empty($category['parent_name']) ? $category['parent_name'] . ' / ' . $category['name'] : $category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="vault-category-options" data-vault-category-options hidden></div>
                </div>
                <?php if (isset($formErrors['category_id'])): ?><small><?= e($formErrors['category_id']) ?></small><?php endif; ?>
            </label>
            <div class="field-wide vault-dynamic-custom-fields <?= isset($formErrors['custom_fields']) ? 'has-error' : '' ?>" data-vault-custom-fields data-vault-custom-values="<?= e(json_encode((array) ($formOld['custom_fields'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
                <?php if (isset($formErrors['custom_fields'])): ?><small><?= e($formErrors['custom_fields']) ?></small><?php endif; ?>
            </div>
            <label class="field"><span>Usuário</span><input type="text" name="username" value="<?= e($formOld['username'] ?? '') ?>" maxlength="190"></label>
            <label class="field <?= isset($formErrors['secret_value']) ? 'has-error' : '' ?>"><span>Senha / segredo</span><input type="password" name="secret_value" maxlength="5000" required><?php if (isset($formErrors['secret_value'])): ?><small><?= e($formErrors['secret_value']) ?></small><?php endif; ?></label>
            <label class="field field-wide"><span>URL</span><input type="text" name="service_url" value="<?= e($formOld['service_url'] ?? '') ?>" maxlength="255"></label>
            <label class="field field-wide"><span>Observações</span><textarea name="notes" rows="3" maxlength="5000"><?= e($formOld['notes'] ?? '') ?></textarea></label>
            <div class="form-actions field-wide"><button class="btn btn-muted" type="button" data-vault-modal-close>Cancelar</button><button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button></div>
        </form>
    </div>
</div>
<?php endif; ?>

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
