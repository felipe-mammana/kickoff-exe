<?php
$formErrors = $_SESSION['vault_form_errors'] ?? [];
$formOld = $_SESSION['vault_form_old'] ?? [];
$openModal = (string) ($_GET['open_modal'] ?? '');
unset($_SESSION['vault_form_errors'], $_SESSION['vault_form_old']);
$categoryErrors = $categoryErrors ?? [];
$categoryOld = $categoryOld ?? [];
$openCategoryModal = $openCategoryModal ?? '';
$iconOptions = $iconOptions ?? [];
$rootCategories = $rootCategories ?? [];
$childCategories = $childCategories ?? [];
$selectedParent = $selectedParent ?? null;
$selectedCategory = $selectedCategory ?? null;
$credentialDefaultCategoryId = (int) ($formOld['category_id'] ?? 0);
if ($credentialDefaultCategoryId <= 0 && $selectedCategory) {
    $credentialDefaultCategoryId = (int) $selectedCategory['id'];
} elseif ($credentialDefaultCategoryId <= 0 && $selectedParent) {
    $credentialDefaultCategoryId = (int) $selectedParent['id'];
}
$categoryDefaultParentId = (int) ($categoryOld['parent_id'] ?? 0);
if ($categoryDefaultParentId <= 0 && $selectedCategory) {
    $categoryDefaultParentId = (int) $selectedCategory['id'];
} elseif ($categoryDefaultParentId <= 0 && $selectedParent) {
    $categoryDefaultParentId = (int) $selectedParent['id'];
}
$openCategoryModalName = (string) ($openCategoryModal ?: '');
if ($openCategoryModalName === '1') {
    $openCategoryModalName = $categoryDefaultParentId > 0 ? 'subcategory' : 'category';
}

$categoryUrl = static function (?int $categoryId = null) use ($company, $filters): string {
    $params = [
        'route' => 'vault.show',
        'id' => (int) $company['id'],
    ];

    if ($categoryId !== null && $categoryId > 0) {
        $params['category_id'] = $categoryId;
    }

    if ($filters['query'] !== '') {
        $params['query'] = $filters['query'];
    }

    return '/?' . http_build_query($params);
};

$parentUrl = static function (?int $parentId = null) use ($company, $filters): string {
    $params = [
        'route' => 'vault.show',
        'id' => (int) $company['id'],
    ];

    if ($parentId !== null && $parentId > 0) {
        $params['parent_id'] = $parentId;
    }

    if ($filters['query'] !== '') {
        $params['query'] = $filters['query'];
    }

    return '/?' . http_build_query($params);
};
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?route=vault.index">Cofre</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span><?= e($company['name']) ?></span>
</nav>

<section class="vault-company-head">
    <a class="icon-btn" href="/?route=vault.index" aria-label="Voltar para o cofre">
        <?= icon('arrow-left') ?>
    </a>
    <div>
        <span class="eyebrow">Cofre da empresa</span>
        <h1><?= e($company['name']) ?></h1>
        <p>Pesquise credenciais por categoria, nome, usuário ou URL.</p>
    </div>
</section>

<section class="content-panel vault-search-panel">
    <form class="vault-search-form" method="get" action="/">
        <input type="hidden" name="route" value="vault.show">
        <input type="hidden" name="id" value="<?= (int) $company['id'] ?>">
        <?php if ($selectedParent): ?>
            <input type="hidden" name="parent_id" value="<?= (int) $selectedParent['id'] ?>">
        <?php endif; ?>
        <?php if (!empty($filters['category_id'])): ?>
            <input type="hidden" name="category_id" value="<?= (int) $filters['category_id'] ?>">
        <?php endif; ?>
        <label class="vault-search-field">
            <?= icon('search') ?>
            <span class="sr-only">Pesquisar credenciais</span>
            <input type="search" name="query" value="<?= e($filters['query']) ?>" placeholder="Pesquisar por categoria ou nome">
        </label>
        <button class="btn btn-primary" type="submit"><?= icon('search') ?><span>Buscar</span></button>
        <?php if ($filters['query'] !== '' || !empty($filters['category_id'])): ?>
            <a class="btn btn-muted" href="/?route=vault.show&id=<?= (int) $company['id'] ?>">Limpar</a>
        <?php endif; ?>
    </form>
</section>

<section class="content-panel vault-categories-panel">
    <div class="panel-header">
        <div>
            <span class="eyebrow">Categorias</span>
            <h2>Acessos da empresa</h2>
            <?php if ($selectedParent): ?>
                <p class="vault-category-path">
                    <a href="/?route=vault.show&id=<?= (int) $company['id'] ?>">Categorias</a>
                    <?= icon('chevron-right', 'breadcrumb-icon') ?>
                    <strong><?= e($selectedParent['name']) ?></strong>
                    <?php if ($selectedCategory): ?>
                        <?= icon('chevron-right', 'breadcrumb-icon') ?>
                        <strong><?= e($selectedCategory['name']) ?></strong>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="vault-panel-actions">
            <button class="btn btn-primary" type="button" data-vault-modal-open="create" data-vault-category-id="<?= (int) $credentialDefaultCategoryId ?>">
                <?= icon('plus') ?><span>Nova credencial</span>
            </button>
            <button class="btn btn-muted" type="button" data-vault-modal-open="category">
                <?= icon('folder-plus') ?><span>Nova categoria</span>
            </button>
        </div>
    </div>

    <div class="vault-company-category-grid">
        <article class="vault-company-category <?= !$selectedParent && empty($filters['category_id']) ? 'active' : '' ?>">
            <a class="vault-category-main" href="<?= e($categoryUrl(null)) ?>">
                <span class="vault-category-title-line">
                    <span class="vault-category-icon"><?= icon('lock') ?></span>
                    <strong>Todas</strong>
                </span>
                <span><?= (int) $totalCredentials ?> item(ns)</span>
            </a>
            <button class="icon-btn compact" type="button" data-vault-modal-open="category-info" data-category-name="Todas" data-category-description="Todas as credenciais da empresa." data-category-count="<?= (int) $totalCredentials ?>" data-category-icon="lock" aria-label="Ver informações de Todas" title="Informações">
                <?= icon('info') ?>
            </button>
        </article>
        <?php foreach ($rootCategories as $category): ?>
            <article class="vault-company-category <?= $selectedParent && (int) $selectedParent['id'] === (int) $category['id'] ? 'active' : '' ?>">
                <a class="vault-category-main" href="<?= e($parentUrl((int) $category['id'])) ?>">
                    <span class="vault-category-title-line">
                        <span class="vault-category-icon"><?= icon($category['icon'] ?: 'lock') ?></span>
                        <strong><?= e($category['name']) ?></strong>
                    </span>
                    <span><?= (int) $category['credentials_count'] ?> item(ns)</span>
                </a>
                <button class="icon-btn compact" type="button" data-vault-modal-open="category-info" data-category-name="<?= e($category['name']) ?>" data-category-description="<?= e($category['description'] ?: 'Sem descrição.') ?>" data-category-count="<?= (int) $category['credentials_count'] ?>" data-category-icon="<?= e($category['icon'] ?: 'lock') ?>" aria-label="Ver informações de <?= e($category['name']) ?>" title="Informações">
                    <?= icon('info') ?>
                </button>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($selectedParent && ($childCategories || !$credentials)): ?>
        <div class="vault-subcategory-panel">
            <div class="vault-subcategory-head">
                <div>
                    <span class="eyebrow">Dentro de <?= e($selectedParent['name']) ?></span>
                    <h3>Subcategorias</h3>
                </div>
                <div class="vault-panel-actions">
                    <button class="btn btn-primary" type="button" data-vault-modal-open="subcategory" data-vault-parent-id="<?= (int) $selectedParent['id'] ?>" data-vault-parent-name="<?= e($selectedParent['name']) ?>">
                        <?= icon('folder-plus') ?><span>Nova subcategoria</span>
                    </button>
                    <a class="btn btn-muted" href="<?= e($categoryUrl((int) $selectedParent['id'])) ?>">
                        <?= icon('filter') ?><span>Ver itens desta categoria</span>
                    </a>
                </div>
            </div>
            <?php if (!$childCategories): ?>
                <div class="empty-state compact">
                    <h3>Nenhuma subcategoria</h3>
                    <p>Use Nova subcategoria para criar uma pasta dentro de <?= e($selectedParent['name']) ?>.</p>
                </div>
            <?php else: ?>
                <div class="vault-company-category-grid vault-subcategory-grid">
                    <?php foreach ($childCategories as $category): ?>
                        <article class="vault-company-category <?= (int) $filters['category_id'] === (int) $category['id'] ? 'active' : '' ?>">
                            <a class="vault-category-main" href="<?= e($categoryUrl((int) $category['id'])) ?>">
                                <span class="vault-category-title-line">
                                    <span class="vault-category-icon"><?= icon($category['icon'] ?: 'lock') ?></span>
                                    <strong><?= e($category['name']) ?></strong>
                                </span>
                                <span><?= (int) $category['credentials_count'] ?> item(ns)</span>
                            </a>
                            <button class="icon-btn compact" type="button" data-vault-modal-open="category-info" data-category-name="<?= e($category['name']) ?>" data-category-description="<?= e($category['description'] ?: 'Sem descrição.') ?>" data-category-count="<?= (int) $category['credentials_count'] ?>" data-category-icon="<?= e($category['icon'] ?: 'lock') ?>" aria-label="Ver informações de <?= e($category['name']) ?>" title="Informações">
                                <?= icon('info') ?>
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php $shouldShowCredentials = !$selectedParent || !empty($filters['category_id']) || $filters['query'] !== ''; ?>
<?php if ($credentials && $shouldShowCredentials): ?>
    <section class="content-panel vault-main-panel">
        <div class="panel-header">
            <div>
                <span class="eyebrow">Credenciais</span>
                <h2>Resultado</h2>
                <p><?= count($credentials) ?> credencial(is) encontrada(s)</p>
            </div>
        </div>
        <div class="inventory-table-wrap">
            <table class="inventory-table vault-table vault-credential-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Usuário</th>
                        <th>Senha</th>
                        <th>URL</th>
                        <th>Atualização</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($credentials as $credential): ?>
                        <tr>
                            <td data-label="Nome">
                                <strong class="truncate-text" title="<?= e($credential['title']) ?>"><?= e($credential['title']) ?></strong>
                            </td>
                            <td data-label="Tipo">
                                <span class="vault-type-chip" title="<?= e($credential['category_name'] ?: 'Sem tipo') ?>">
                                    <?= icon($credential['category_icon'] ?: 'lock') ?>
                                    <span><?= e($credential['category_name'] ?: 'Sem tipo') ?></span>
                                </span>
                            </td>
                            <td data-label="Usuário">
                                <?php if (!empty($credential['username'])): ?>
                                    <span class="vault-copy-cell">
                                        <span class="truncate-text" title="<?= e($credential['username']) ?>"><?= e($credential['username']) ?></span>
                                        <button class="icon-btn compact" type="button" data-copy-value="<?= e($credential['username']) ?>" aria-label="Copiar usuário" title="Copiar usuário">
                                            <?= icon('copy') ?>
                                        </button>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-label="Senha">
                                <span class="vault-secret-cell" data-vault-secret-cell>
                                    <input type="password" value="" placeholder="••••••••" readonly data-vault-secret-output>
                                    <input type="hidden" value="<?= e(csrf_token()) ?>" data-vault-secret-csrf>
                                    <button
                                        class="password-toggle-icon"
                                        type="button"
                                        data-vault-secret-toggle
                                        data-vault-secret-id="<?= (int) $credential['id'] ?>"
                                        aria-label="Mostrar senha"
                                        title="Mostrar senha"
                                    >
                                        <?= icon('eye') ?>
                                    </button>
                                    <button
                                        class="password-toggle-icon"
                                        type="button"
                                        data-vault-secret-copy
                                        data-vault-secret-id="<?= (int) $credential['id'] ?>"
                                        aria-label="Copiar senha"
                                        title="Copiar senha"
                                    >
                                        <?= icon('copy') ?>
                                    </button>
                                </span>
                            </td>
                            <td data-label="URL">
                                <?php $safeUrl = safe_external_url($credential['service_url'] ?? null); ?>
                                <?php if ($safeUrl !== null): ?>
                                    <span class="vault-copy-cell">
                                        <a class="vault-inline-link truncate-text" href="<?= e($safeUrl) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($credential['service_url']) ?>">
                                            <?= e($credential['service_url']) ?>
                                        </a>
                                        <button class="icon-btn compact" type="button" data-copy-value="<?= e($credential['service_url']) ?>" aria-label="Copiar URL" title="Copiar URL">
                                            <?= icon('copy') ?>
                                        </button>
                                    </span>
                                <?php elseif (!empty($credential['service_url'])): ?>
                                    <span class="vault-copy-cell">
                                        <span class="truncate-text" title="<?= e($credential['service_url']) ?>"><?= e($credential['service_url']) ?></span>
                                        <button class="icon-btn compact" type="button" data-copy-value="<?= e($credential['service_url']) ?>" aria-label="Copiar URL" title="Copiar URL">
                                            <?= icon('copy') ?>
                                        </button>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td data-label="Atualização"><?= e($credential['updated_at'] ?: '-') ?></td>
                            <td data-label="Ações">
                                <div class="table-actions">
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        data-vault-modal-open="edit"
                                        data-vault-id="<?= (int) $credential['id'] ?>"
                                        data-vault-title="<?= e($credential['title']) ?>"
                                        data-vault-category-id="<?= (int) ($credential['category_id'] ?? 0) ?>"
                                        data-vault-username="<?= e($credential['username'] ?? '') ?>"
                                        data-vault-service-url="<?= e($credential['service_url'] ?? '') ?>"
                                        data-vault-notes="<?= e($credential['notes'] ?? '') ?>"
                                        aria-label="Editar credencial"
                                        title="Editar"
                                    >
                                        <?= icon('edit-3') ?>
                                    </button>
                                    <form action="/?route=vault.deactivate" method="post" data-confirm="Desativar esta credencial?">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $credential['id'] ?>">
                                        <button class="icon-btn danger" type="submit" aria-label="Desativar credencial" title="Desativar">
                                            <?= icon('trash-2') ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<?php $attachmentCategory = $selectedCategory ?: $selectedParent; ?>
<?php if (!empty($attachmentCategoryId) && $attachmentCategory): ?>
    <section class="content-panel vault-main-panel company-attachments-panel">
        <div class="panel-header">
            <div>
                <span class="eyebrow">Anexos</span>
                <h2>Arquivos de <?= e($attachmentCategory['name']) ?></h2>
                <p><?= count($attachments ?? []) ?> arquivo(s) vinculado(s)</p>
            </div>
            <button class="btn btn-muted" type="button" data-attachment-form-toggle aria-expanded="false">
                <?= icon('plus') ?><span>Adicionar anexo</span>
            </button>
        </div>

        <form class="company-attachment-form" action="/?route=companies.attachments.store" method="post" enctype="multipart/form-data" data-attachment-form-panel hidden>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <input type="hidden" name="category_id" value="<?= (int) $attachmentCategoryId ?>">
            <div class="attachment-form-main">
                <label class="upload-drop compact">
                    <span class="upload-icon"><?= icon('file-text') ?></span>
                    <strong>Selecionar arquivo</strong>
                    <small>PDF, Office, CSV, TXT, imagem ou ZIP até <?= e(format_file_size(COMPANY_ATTACHMENT_MAX_BYTES)) ?></small>
                    <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.zip" required data-attachment-input>
                </label>
                <label class="field">
                    <span>Descrição opcional</span>
                    <input type="text" name="description" maxlength="255" placeholder="Ex.: contrato, acesso, evidência">
                </label>
            </div>
            <div class="attachment-form-footer">
                <div class="attachment-selected" data-attachment-selected hidden>
                    <?= icon('file-check') ?>
                    <span data-attachment-name>Arquivo selecionado</span>
                    <button type="button" data-attachment-clear aria-label="Remover arquivo selecionado"><?= icon('x') ?></button>
                </div>
                <button class="btn btn-primary" type="submit"><?= icon('upload') ?><span>Enviar anexo</span></button>
            </div>
        </form>

        <?php if (empty($attachments)): ?>
            <div class="empty-state compact">
                <h3>Nenhum anexo nesta categoria</h3>
                <p>Envie arquivos relacionados a <?= e($attachmentCategory['name']) ?>.</p>
            </div>
        <?php else: ?>
            <div class="inventory-table-wrap">
                <table class="inventory-table company-attachments-table">
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Descrição</th>
                            <th>Tamanho</th>
                            <th>Enviado por</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attachments as $attachment): ?>
                            <tr>
                                <td data-label="Arquivo"><strong><?= e($attachment['original_name']) ?></strong></td>
                                <td data-label="Descrição"><?= e($attachment['description'] ?: '-') ?></td>
                                <td data-label="Tamanho"><?= e(format_file_size((int) $attachment['file_size'])) ?></td>
                                <td data-label="Enviado por"><?= e($attachment['uploaded_by_name'] ?: '-') ?></td>
                                <td data-label="Data"><?= e($attachment['created_at'] ?: '-') ?></td>
                                <td data-label="Ações">
                                    <div class="table-actions">
                                        <a class="icon-btn" href="/?route=companies.attachments.download&id=<?= (int) $attachment['id'] ?>" aria-label="Baixar anexo" title="Baixar">
                                            <?= icon('download') ?>
                                        </a>
                                        <form action="/?route=companies.attachments.delete" method="post" data-confirm="Remover este anexo?">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $attachment['id'] ?>">
                                            <button class="icon-btn danger" type="submit" aria-label="Remover anexo" title="Remover">
                                                <?= icon('trash-2') ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<div class="company-modal vault-modal" data-vault-modal="create" <?= $openModal === 'create' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vault-create-title">
        <header class="modal-head">
            <div>
                <span class="eyebrow">Cofre</span>
                <h2 id="vault-create-title">Nova credencial</h2>
            </div>
            <button class="icon-btn" type="button" data-vault-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form vault-credential-form vault-credential-grid" action="/?route=vault.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <label class="field <?= isset($formErrors['title']) ? 'has-error' : '' ?>">
                <span>Nome</span>
                <input type="text" name="title" value="<?= e($formOld['title'] ?? '') ?>" required data-vault-modal-focus>
                <?php if (isset($formErrors['title'])): ?><small><?= e($formErrors['title']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['category_id']) ? 'has-error' : '' ?>">
                <span>Tipo de credencial</span>
                <select name="category_id" data-vault-create-category>
                    <option value="">Sem tipo</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= $credentialDefaultCategoryId === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['category_id'])): ?><small><?= e($formErrors['category_id']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['username']) ? 'has-error' : '' ?>">
                <span>Usuário</span>
                <input type="text" name="username" value="<?= e($formOld['username'] ?? '') ?>">
                <?php if (isset($formErrors['username'])): ?><small><?= e($formErrors['username']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['secret_value']) ? 'has-error' : '' ?>">
                <span>Senha / segredo</span>
                <input type="password" name="secret_value" required>
                <?php if (isset($formErrors['secret_value'])): ?><small><?= e($formErrors['secret_value']) ?></small><?php endif; ?>
            </label>
            <label class="field field-wide <?= isset($formErrors['service_url']) ? 'has-error' : '' ?>">
                <span>URL</span>
                <input type="text" name="service_url" value="<?= e($formOld['service_url'] ?? '') ?>">
                <?php if (isset($formErrors['service_url'])): ?><small><?= e($formErrors['service_url']) ?></small><?php endif; ?>
            </label>
            <label class="field field-wide <?= isset($formErrors['notes']) ? 'has-error' : '' ?>">
                <span>Observações</span>
                <textarea name="notes" rows="3"><?= e($formOld['notes'] ?? '') ?></textarea>
                <?php if (isset($formErrors['notes'])): ?><small><?= e($formErrors['notes']) ?></small><?php endif; ?>
            </label>
            <div class="form-actions field-wide">
                <button class="btn btn-muted" type="button" data-vault-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </div>
        </form>
    </div>
</div>

<div class="company-modal vault-modal" data-vault-modal="category" <?= $openCategoryModalName === 'category' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vault-category-title">
        <header class="modal-head">
            <div>
                <span class="eyebrow">Pasta do cofre</span>
                <h2 id="vault-category-title">Nova categoria</h2>
            </div>
            <button class="icon-btn" type="button" data-vault-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form vault-credential-form" action="/?route=vault.categories.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <input type="hidden" name="parent_id" value="">
            <label class="field <?= isset($categoryErrors['name']) ? 'has-error' : '' ?>">
                <span>Nome da categoria</span>
                <input type="text" name="name" value="<?= e($categoryOld['name'] ?? '') ?>" required data-vault-modal-focus>
                <?php if (isset($categoryErrors['name'])): ?><small><?= e($categoryErrors['name']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($categoryErrors['description']) ? 'has-error' : '' ?>">
                <span>Descrição</span>
                <input type="text" name="description" value="<?= e($categoryOld['description'] ?? '') ?>">
                <?php if (isset($categoryErrors['description'])): ?><small><?= e($categoryErrors['description']) ?></small><?php endif; ?>
            </label>
            <fieldset class="vault-icon-picker">
                <legend>Ícone</legend>
                <?php foreach ($iconOptions as $iconName => $iconLabel): ?>
                    <label>
                        <input type="radio" name="icon" value="<?= e($iconName) ?>" <?= ($categoryOld['icon'] ?? 'folder') === $iconName ? 'checked' : '' ?>>
                        <span><?= icon($iconName) ?><small><?= e($iconLabel) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <div class="form-actions">
                <button class="btn btn-muted" type="button" data-vault-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </div>
        </form>
    </div>
</div>

<div class="company-modal vault-modal" data-vault-modal="subcategory" <?= $openCategoryModalName === 'subcategory' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vault-subcategory-title">
        <header class="modal-head">
            <div>
                <span class="eyebrow">Subpasta do cofre</span>
                <h2 id="vault-subcategory-title">Nova subcategoria</h2>
            </div>
            <button class="icon-btn" type="button" data-vault-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form vault-credential-form" action="/?route=vault.categories.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <input type="hidden" name="parent_id" value="<?= (int) $categoryDefaultParentId ?>" data-vault-category-parent>
            <div class="settings-readonly">
                <p>Criando dentro de <strong data-vault-subcategory-parent-name><?= e($selectedCategory['name'] ?? $selectedParent['name'] ?? 'categoria selecionada') ?></strong>.</p>
            </div>
            <label class="field <?= isset($categoryErrors['name']) ? 'has-error' : '' ?>">
                <span>Nome da subcategoria</span>
                <input type="text" name="name" value="<?= e($categoryOld['name'] ?? '') ?>" required data-vault-modal-focus>
                <?php if (isset($categoryErrors['name'])): ?><small><?= e($categoryErrors['name']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($categoryErrors['description']) ? 'has-error' : '' ?>">
                <span>Descrição</span>
                <input type="text" name="description" value="<?= e($categoryOld['description'] ?? '') ?>">
                <?php if (isset($categoryErrors['description'])): ?><small><?= e($categoryErrors['description']) ?></small><?php endif; ?>
            </label>
            <fieldset class="vault-icon-picker">
                <legend>Ícone</legend>
                <?php foreach ($iconOptions as $iconName => $iconLabel): ?>
                    <label>
                        <input type="radio" name="icon" value="<?= e($iconName) ?>" <?= ($categoryOld['icon'] ?? 'folder') === $iconName ? 'checked' : '' ?>>
                        <span><?= icon($iconName) ?><small><?= e($iconLabel) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <div class="form-actions">
                <button class="btn btn-muted" type="button" data-vault-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </div>
        </form>
    </div>
</div>

<div class="company-modal vault-modal" data-vault-modal="category-info" hidden>
    <div class="company-modal-dialog vault-info-dialog" role="dialog" aria-modal="true" aria-labelledby="vault-category-info-title">
        <header class="modal-head">
            <div>
                <span class="eyebrow">Informações</span>
                <h2 id="vault-category-info-title" data-vault-info-name>Categoria</h2>
            </div>
            <button class="icon-btn" type="button" data-vault-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <div class="vault-info-body">
            <span class="vault-category-icon" data-vault-info-icon><?= icon('lock') ?></span>
            <div>
                <strong data-vault-info-count>0 item(ns)</strong>
                <p data-vault-info-description>Sem descrição.</p>
            </div>
        </div>
    </div>
</div>

<div class="company-modal vault-modal" data-vault-modal="edit" <?= $openModal === 'edit' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="vault-edit-title">
        <header class="modal-head">
            <div>
                <span class="eyebrow">Cofre</span>
                <h2 id="vault-edit-title">Editar credencial</h2>
            </div>
            <button class="icon-btn" type="button" data-vault-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form vault-credential-form vault-credential-grid" action="/?route=vault.update" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int) ($formOld['id'] ?? 0) ?>" data-vault-edit-id>
            <label class="field <?= isset($formErrors['title']) ? 'has-error' : '' ?>">
                <span>Nome</span>
                <input type="text" name="title" value="<?= e($formOld['title'] ?? '') ?>" required data-vault-edit-title>
                <?php if (isset($formErrors['title'])): ?><small><?= e($formErrors['title']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['category_id']) ? 'has-error' : '' ?>">
                <span>Tipo de credencial</span>
                <select name="category_id" data-vault-edit-category>
                    <option value="">Sem tipo</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) ($formOld['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($formErrors['category_id'])): ?><small><?= e($formErrors['category_id']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['username']) ? 'has-error' : '' ?>">
                <span>Usuário</span>
                <input type="text" name="username" value="<?= e($formOld['username'] ?? '') ?>" data-vault-edit-username>
                <?php if (isset($formErrors['username'])): ?><small><?= e($formErrors['username']) ?></small><?php endif; ?>
            </label>
            <label class="field <?= isset($formErrors['secret_value']) ? 'has-error' : '' ?>">
                <span>Nova senha / segredo</span>
                <input type="password" name="secret_value" placeholder="Deixe vazio para manter a senha atual">
                <?php if (isset($formErrors['secret_value'])): ?><small><?= e($formErrors['secret_value']) ?></small><?php endif; ?>
            </label>
            <label class="field field-wide <?= isset($formErrors['service_url']) ? 'has-error' : '' ?>">
                <span>URL</span>
                <input type="text" name="service_url" value="<?= e($formOld['service_url'] ?? '') ?>" data-vault-edit-service-url>
                <?php if (isset($formErrors['service_url'])): ?><small><?= e($formErrors['service_url']) ?></small><?php endif; ?>
            </label>
            <label class="field field-wide <?= isset($formErrors['notes']) ? 'has-error' : '' ?>">
                <span>Observações</span>
                <textarea name="notes" rows="3" data-vault-edit-notes><?= e($formOld['notes'] ?? '') ?></textarea>
                <?php if (isset($formErrors['notes'])): ?><small><?= e($formErrors['notes']) ?></small><?php endif; ?>
            </label>
            <div class="form-actions field-wide">
                <button class="btn btn-muted" type="button" data-vault-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </div>
        </form>
    </div>
</div>
