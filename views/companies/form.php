<?php
$isEdit = !empty($company['id']);
$value = static fn (string $field): string => e((string) ($company[$field] ?? ''));
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
        <p>Defina a organizacao e o padrao de etiqueta usado nos dispositivos.</p>
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
                <h2>Identificacao</h2>
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
                <span>Padrao de etiqueta</span>
                <input type="text" name="tag_pattern" value="<?= $value('tag_pattern') ?>" placeholder="NOTE-0001, CPU-0001, IMP-0001">
            </label>
        </div>
    </section>

    <section class="asset-form-section">
        <header class="asset-section-head">
            <span class="step-number">2</span>
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
        <button class="btn btn-primary" type="submit"><?= icon('save') ?><span><?= $isEdit ? 'Salvar alteracoes' : 'Cadastrar empresa' ?></span></button>
    </div>
</form>
