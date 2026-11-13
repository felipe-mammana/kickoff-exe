<?php
$companies = is_array($companies ?? null) ? $companies : [];
$categories = is_array($categories ?? null) ? $categories : [];
$categoryFieldConfig = is_array($categoryFieldConfig ?? null) ? $categoryFieldConfig : [];
$preview = is_array($preview ?? null) ? $preview : null;
$records = is_array($preview['records'] ?? null) ? $preview['records'] : [];
$previewToken = (string) ($preview['token'] ?? '');
$parentNames = [];
foreach ($categories as $category) {
    if (empty($category['parent_id'])) $parentNames[(int) $category['id']] = (string) $category['name'];
}
$categoryLabel = static function (array $category) use ($parentNames): string {
    $parentId = (int) ($category['parent_id'] ?? 0);
    return $parentId > 0 && isset($parentNames[$parentId])
        ? $parentNames[$parentId] . ' / ' . (string) $category['name']
        : (string) $category['name'];
};
$renderCustomFields = static function (int $index, int $categoryId, array $values) use ($categoryFieldConfig): void {
    foreach ((array) ($categoryFieldConfig[(string) $categoryId] ?? []) as $field) {
        $key = (string) ($field['key'] ?? '');
        if ($key === '') continue;
        $label = (string) ($field['label'] ?? 'Campo');
        $value = (string) ($values[$key] ?? '');
        $name = 'records[' . $index . '][custom_fields][' . $key . ']';
        $type = (string) ($field['type'] ?? 'text');
        ?>
        <label class="field">
            <span><?= e($label) ?><?= !empty($field['required']) ? ' *' : '' ?></span>
            <?php if ($type === 'textarea'): ?>
                <textarea name="<?= e($name) ?>" rows="2" maxlength="5000" <?= !empty($field['required']) ? 'required' : '' ?>><?= e($value) ?></textarea>
            <?php else: ?>
                <input type="<?= $type === 'email' ? 'email' : ($type === 'url' ? 'url' : ($type === 'number' ? 'number' : 'text')) ?>" name="<?= e($name) ?>" value="<?= e($value) ?>" maxlength="255" placeholder="<?= e((string) ($field['placeholder'] ?? '')) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
            <?php endif; ?>
        </label>
        <?php
    }
};
?>

<script type="application/json" data-vault-import-field-config><?= json_encode($categoryFieldConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<nav class="breadcrumbs" aria-label="Navegação estrutural">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?route=vault.index">Cofre</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <strong>Importar credenciais</strong>
</nav>

<section class="vault-import-head">
    <div>
        <span class="eyebrow">Cofre de senhas</span>
        <h1>Importar credenciais</h1>
        <p>Revise cada registro antes de confirmar. O arquivo enviado não é armazenado.</p>
    </div>
    <a class="btn btn-muted" href="/?route=vault.index"><?= icon('arrow-left') ?><span>Voltar ao cofre</span></a>
</section>

<ol class="vault-import-steps" aria-label="Etapas da importação">
    <li class="<?= $preview ? 'complete' : 'active' ?>"><span>1</span><strong>Selecionar arquivo</strong></li>
    <li class="<?= $preview ? 'active' : '' ?>"><span>2</span><strong>Revisar credenciais</strong></li>
    <li><span>3</span><strong>Confirmar importação</strong></li>
</ol>

<?php if (!$preview): ?>
    <section class="content-panel vault-import-upload-panel">
        <div class="asset-panel-head">
            <div>
                <span class="eyebrow">Etapa 1</span>
                <h2>Selecionar arquivo</h2>
                <p>Formatos aceitos: DOCX, XLSX e CSV, com até 8 MB.</p>
            </div>
        </div>
        <form class="company-form vault-import-upload-form" action="/?route=vault.import.preview" method="post" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="field">
                <span>Empresa padrão</span>
                <select name="company_id" required>
                    <option value="">Selecione a empresa</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= (int) $company['id'] ?>" <?= (int) $defaultCompanyId === (int) $company['id'] ? 'selected' : '' ?>><?= e((string) $company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small>Você poderá alterar a empresa individualmente na prévia.</small>
            </label>
            <div class="vault-import-drop-zone" data-vault-import-drop role="button" tabindex="0" aria-label="Selecionar arquivo para importação">
                <span class="upload-icon"><?= icon('file-up') ?></span>
                <strong>Escolha o documento ou planilha</strong>
                <small>O processamento ocorre somente para gerar a prévia e o arquivo temporário é descartado pelo PHP ao fim da requisição.</small>
                <button class="btn btn-muted" type="button" data-vault-import-browse><?= icon('folder') ?><span>Selecionar arquivo</span></button>
                <input type="file" name="import_file" accept=".docx,.xlsx,.csv" required data-vault-import-file>
                <span class="vault-import-file-name" data-vault-import-file-name>Nenhum arquivo selecionado</span>
                <span class="vault-import-file-meta" data-vault-import-file-meta>DOCX, XLSX ou CSV · até 8 MB</span>
            </div>
            <div class="form-actions form-actions-bar">
                <a class="btn btn-muted" href="/?route=vault.index">Cancelar</a>
                <button class="btn btn-primary" type="submit"><?= icon('scan-search') ?><span>Processar e revisar</span></button>
            </div>
        </form>
    </section>
<?php else: ?>
    <section class="content-panel vault-import-review-panel">
        <div class="asset-panel-head vault-import-review-head">
            <div>
                <span class="eyebrow">Etapa 2</span>
                <h2>Revisar credenciais</h2>
                <p><?= count($records) ?> registro(s) detectado(s) em <?= e((string) ($preview['metadata']['format'] ?? 'arquivo')) ?>.</p>
            </div>
            <label class="vault-import-select-all"><input type="checkbox" checked data-vault-import-select-all><span>Selecionar todos</span></label>
        </div>

        <?php foreach ((array) ($preview['metadata']['warnings'] ?? []) as $warning): ?>
            <p class="vault-import-banner warning"><?= e((string) $warning) ?></p>
        <?php endforeach; ?>

        <form id="vault-import-confirm-form" class="vault-import-review-form" action="/?route=vault.import.confirm" method="post" autocomplete="off" data-vault-import-form data-confirm="Confirmar a importação das credenciais selecionadas?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="preview_token" value="<?= e($previewToken) ?>">
            <div class="vault-import-record-list" data-vault-import-list>
                <?php foreach ($records as $record): ?>
                    <?php $index = (int) ($record['source_index'] ?? 0); $categoryId = (int) ($record['category_id'] ?? 0); ?>
                    <article class="vault-import-record <?= !empty($record['warnings']) || !empty($record['duplicate_reasons']) ? 'needs-review' : '' ?>" data-vault-import-record data-record-index="<?= $index ?>">
                        <header class="vault-import-record-head">
                            <label class="vault-import-record-select">
                                <input type="checkbox" name="records[<?= $index ?>][selected]" value="1" <?= !empty($record['selected']) ? 'checked' : '' ?> data-vault-import-select>
                                <span>Importar registro <?= $index + 1 ?></span>
                            </label>
                            <span class="vault-import-source"><?= e((string) ($record['source'] ?? 'Arquivo')) ?></span>
                            <button class="icon-btn danger" type="button" data-vault-import-remove aria-label="Remover da prévia" title="Remover da prévia"><?= icon('trash-2') ?></button>
                        </header>

                        <?php if (!empty($record['warnings'])): ?>
                            <div class="vault-import-warning-list" role="alert">
                                <?php foreach ((array) $record['warnings'] as $warning): ?><span><?= icon('triangle-alert') ?><?= e((string) $warning) ?></span><?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($record['duplicate_reasons'])): ?>
                            <div class="vault-import-duplicate" role="alert">
                                <strong><?= icon('copy-check') ?>Possível duplicidade</strong>
                                <span><?= e(implode(' ', (array) $record['duplicate_reasons'])) ?></span>
                                <label><input type="checkbox" name="records[<?= $index ?>][allow_duplicate]" value="1"><span>Importar mesmo assim</span></label>
                            </div>
                        <?php endif; ?>

                        <div class="vault-import-grid">
                            <label class="field">
                                <span>Nome *</span>
                                <input type="text" name="records[<?= $index ?>][title]" value="<?= e((string) ($record['title'] ?? '')) ?>" maxlength="160" required>
                            </label>
                            <label class="field">
                                <span>Usuário</span>
                                <input type="text" name="records[<?= $index ?>][username]" value="<?= e((string) ($record['username'] ?? '')) ?>" maxlength="190">
                            </label>
                            <label class="field">
                                <span>Empresa *</span>
                                <select name="records[<?= $index ?>][company_id]" required data-vault-import-company>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?= (int) $company['id'] ?>" <?= (int) ($record['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>><?= e((string) $company['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Categoria / subcategoria *</span>
                                <select name="records[<?= $index ?>][category_id]" required data-vault-import-category>
                                    <option value="">Selecione</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= (int) $category['id'] ?>" data-company-ids="<?= e((string) ($category['allowed_company_ids'] ?? '')) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($categoryLabel($category)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Senha / segredo *</span>
                                <span class="vault-import-secret-input">
                                    <input type="password" name="records[<?= $index ?>][secret_value]" value="<?= e((string) ($record['secret_value'] ?? '')) ?>" maxlength="5000" autocomplete="new-password" required data-vault-import-secret>
                                    <button class="password-toggle-icon" type="button" data-vault-import-secret-toggle aria-label="Mostrar senha" title="Mostrar senha"><?= icon('eye') ?></button>
                                </span>
                            </label>
                            <label class="field">
                                <span>URL</span>
                                <input type="text" name="records[<?= $index ?>][service_url]" value="<?= e((string) ($record['service_url'] ?? '')) ?>" maxlength="255">
                            </label>
                            <label class="field"><span>IP interno</span><input type="text" name="records[<?= $index ?>][ip_internal]" value="<?= e((string) ($record['ip_internal'] ?? '')) ?>" maxlength="255" data-vault-import-standard="ip_internal"></label>
                            <label class="field"><span>IP externo</span><input type="text" name="records[<?= $index ?>][ip_external]" value="<?= e((string) ($record['ip_external'] ?? '')) ?>" maxlength="255" data-vault-import-standard="ip_external"></label>
                            <label class="field"><span>MAC</span><input type="text" name="records[<?= $index ?>][mac]" value="<?= e((string) ($record['mac'] ?? '')) ?>" maxlength="255" data-vault-import-standard="mac"></label>
                            <label class="field"><span>SSID</span><input type="text" name="records[<?= $index ?>][ssid]" value="<?= e((string) ($record['ssid'] ?? '')) ?>" maxlength="255" data-vault-import-standard="ssid"></label>
                            <div class="vault-import-custom-fields field-wide" data-vault-import-custom-fields>
                                <?php $renderCustomFields($index, $categoryId, (array) ($record['custom_fields'] ?? [])); ?>
                            </div>
                            <label class="field field-wide">
                                <span>Observações</span>
                                <textarea name="records[<?= $index ?>][notes]" rows="3" maxlength="5000"><?= e((string) ($record['notes'] ?? '')) ?></textarea>
                            </label>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </form>

        <form id="vault-import-cancel-form" action="/?route=vault.import.cancel" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        </form>
        <footer class="vault-import-confirm-bar">
            <div>
                <strong data-vault-import-selected-count>0 selecionado(s)</strong>
                <span>Nenhuma credencial será salva antes desta confirmação.</span>
            </div>
            <div class="form-actions">
                <button class="btn btn-muted" type="submit" form="vault-import-cancel-form"><?= icon('x') ?><span>Cancelar importação</span></button>
                <button class="btn btn-primary" type="submit" form="vault-import-confirm-form"><?= icon('shield-check') ?><span>Confirmar e salvar no cofre</span></button>
            </div>
        </footer>
    </section>
<?php endif; ?>
