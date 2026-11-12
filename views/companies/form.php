<?php
$isEdit = !empty($company['id']);
$value = static fn (string $field): string => e((string) ($company[$field] ?? ''));
$vaultCategories = is_array($vaultCategories ?? null) ? $vaultCategories : [];
$selectedVaultCategoryIds = array_map('intval', is_array($selectedVaultCategoryIds ?? null) ? $selectedVaultCategoryIds : []);
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?route=companies.index">Empresas</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span><?= $isEdit ? 'Editar' : 'Nova empresa' ?></span>
</nav>

<section class="asset-page-head">
    <div>
        <h1><?= $isEdit ? 'Editar empresa' : 'Cadastrar empresa' ?></h1>
        <p>Defina a organização e o padrão de etiqueta usado nos dispositivos.</p>
    </div>
    <a class="btn btn-muted" href="<?= $isEdit ? '/?route=companies.show&id=' . (int) $company['id'] : '/?route=companies.index' ?>">
        <?= icon('chevron-left') ?><span>Voltar</span>
    </a>
</section>

<?php if ($errors): ?>
    <div class="alert alert-danger">Revise os campos destacados antes de salvar.</div>
<?php endif; ?>

<form class="company-form" action="<?= e($action) ?>" method="post" novalidate>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

    <section class="asset-form-section">
        <header class="asset-section-head">
            <span class="step-number">1</span>
            <div>
                <h2>Identificação</h2>
                <p>Dados principais da empresa no inventario.</p>
            </div>
        </header>

        <div class="fields-grid">
            <label class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
                <span>Nome da empresa</span>
                <input type="text" name="name" value="<?= $value('name') ?>" placeholder="Ex.: Global Logistics S.A." required>
                <?php if (isset($errors['name'])): ?>
                    <small><?= e($errors['name']) ?></small>
                <?php endif; ?>
            </label>

            <label class="field">
                <span>Codigo da empresa para etiqueta</span>
                <input type="text" name="tag_pattern" value="<?= $value('tag_pattern') ?>" placeholder="EXE">
            </label>
        </div>
    </section>

    <section class="asset-form-section">
        <header class="asset-section-head">
            <span class="step-number">2</span>
            <div>
                <h2>Categorias do Cofre</h2>
                <p>Escolha, se necessário, quais categorias globais estarão disponíveis para as credenciais desta empresa.</p>
            </div>
        </header>

        <div class="company-vault-category-grid <?= isset($errors['vault_category_ids']) ? 'has-error' : '' ?>" data-company-vault-categories>
            <?php foreach ($vaultCategories as $vaultCategory): ?>
                <?php $rootId = (int) $vaultCategory['id']; $rootSelected = in_array($rootId, $selectedVaultCategoryIds, true); ?>
                <div class="company-vault-category-group">
                    <label class="check-card">
                        <input type="checkbox" name="vault_category_ids[]" value="<?= $rootId ?>" data-company-vault-root <?= $rootSelected ? 'checked' : '' ?>>
                        <span><?= icon((string) ($vaultCategory['icon'] ?? 'folder')) ?> <?= e((string) $vaultCategory['name']) ?></span>
                    </label>
                    <?php if (!empty($vaultCategory['children'])): ?>
                        <div class="company-vault-subcategory-list">
                            <?php foreach ($vaultCategory['children'] as $vaultSubcategory): ?>
                                <label class="check-card compact">
                                    <input type="checkbox" name="vault_category_ids[]" value="<?= (int) $vaultSubcategory['id'] ?>" data-company-vault-child data-parent-id="<?= $rootId ?>" <?= in_array((int) $vaultSubcategory['id'], $selectedVaultCategoryIds, true) ? 'checked' : '' ?> <?= !$rootSelected ? 'disabled' : '' ?>>
                                    <span><?= icon((string) ($vaultSubcategory['icon'] ?? 'folder')) ?> <?= e((string) $vaultSubcategory['name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['vault_category_ids'])): ?><p class="field-error-message"><?= e($errors['vault_category_ids']) ?></p><?php endif; ?>
    </section>

    <section class="asset-form-section">
        <header class="asset-section-head">
            <span class="step-number">3</span>
            <div>
                <h2>Status operacional</h2>
                <p>Empresas inativas permanecem no historico, mas saem do fluxo principal.</p>
            </div>
        </header>

        <label class="check-card company-status-card">
            <input type="checkbox" name="is_active" <?= !isset($company['is_active']) || !empty($company['is_active']) ? 'checked' : '' ?>>
            <span>Empresa ativa</span>
        </label>
    </section>

    <div class="form-actions-bar company-actions">
        <a class="btn btn-muted" href="<?= $isEdit ? '/?route=companies.show&id=' . (int) $company['id'] : '/?route=companies.index' ?>">Cancelar</a>
        <button class="btn btn-primary" type="submit"><?= icon('save') ?><span><?= $isEdit ? 'Salvar alterações' : 'Cadastrar empresa' ?></span></button>
    </div>
</form>
