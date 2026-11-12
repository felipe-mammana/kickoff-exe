<?php
$categoryRows = is_array($credentialCategories ?? null) ? $credentialCategories : [];
$selectedCredentialCategoryId = (int) ($selectedCredentialCategoryId ?? 0);
$selectedRootCategoryId = 0;
$selectedSubcategoryId = 0;
foreach ($categoryRows as $categoryRow) {
    if ((int) $categoryRow['id'] !== $selectedCredentialCategoryId) continue;
    if (!empty($categoryRow['parent_id'])) {
        $selectedRootCategoryId = (int) $categoryRow['parent_id'];
        $selectedSubcategoryId = (int) $categoryRow['id'];
    } else {
        $selectedRootCategoryId = (int) $categoryRow['id'];
    }
    break;
}
?>
<input type="hidden" name="category_id" value="<?= $selectedCredentialCategoryId ?>" data-vault-effective-category>
<label class="field <?= isset($formErrors['category_id']) ? 'has-error' : '' ?>">
    <span>Categoria</span>
    <select name="category_root_id" required data-vault-category-root>
        <option value="">Selecione uma categoria</option>
        <?php foreach ($categoryRows as $categoryRow): ?>
            <?php if (!empty($categoryRow['parent_id'])) continue; ?>
            <option value="<?= (int) $categoryRow['id'] ?>" data-company-ids="<?= e((string) ($categoryRow['allowed_company_ids'] ?? '')) ?>" <?= $selectedRootCategoryId === (int) $categoryRow['id'] ? 'selected' : '' ?>>
                <?= e((string) $categoryRow['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (isset($formErrors['category_id'])): ?><small><?= e($formErrors['category_id']) ?></small><?php endif; ?>
</label>
<label class="field" data-vault-subcategory-field <?= $selectedRootCategoryId > 0 ? '' : 'hidden' ?>>
    <span>Subcategoria</span>
    <select name="subcategory_id" data-vault-category-child>
        <option value="">Sem subcategoria</option>
        <?php foreach ($categoryRows as $categoryRow): ?>
            <?php if (empty($categoryRow['parent_id'])) continue; ?>
            <option value="<?= (int) $categoryRow['id'] ?>" data-parent-id="<?= (int) $categoryRow['parent_id'] ?>" data-company-ids="<?= e((string) ($categoryRow['allowed_company_ids'] ?? '')) ?>" <?= $selectedSubcategoryId === (int) $categoryRow['id'] ? 'selected' : '' ?>>
                <?= e((string) $categoryRow['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <small data-vault-subcategory-help>Opcional.</small>
</label>
