<span class="password-requirements" data-password-requirements>
    <?php foreach (['upper' => 'Letra maiúscula', 'lower' => 'Letra minúscula', 'number' => 'Número', 'special' => 'Caractere especial', 'length' => 'Mínimo de 8 caracteres'] as $rule => $label): ?>
        <span class="password-requirement" data-password-rule="<?= $rule ?>">
            <span class="password-requirement-symbol" aria-hidden="true">&#10005;</span>
            <span><?= $label ?></span>
            <span class="password-requirement-status visually-hidden">Pendente</span>
        </span>
    <?php endforeach; ?>
</span>
