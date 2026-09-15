<div class="company-modal account-password-modal" data-account-password-modal hidden>
    <div class="company-modal-dialog confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="<?= e($confirmationId) ?>-title" aria-describedby="<?= e($confirmationId) ?>-description">
        <header class="modal-head">
            <h2 id="<?= e($confirmationId) ?>-title"><?= e($confirmationTitle) ?></h2>
            <button class="icon-btn" type="button" data-account-password-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <div class="confirm-modal-body">
            <p id="<?= e($confirmationId) ?>-description"><?= e($confirmationDescription) ?></p>
            <label class="field">
                <span>Senha atual</span>
                <input type="password" name="current_password" autocomplete="current-password" required disabled data-account-password-input>
            </label>
        </div>
        <div class="form-actions confirm-modal-actions">
            <button class="btn btn-muted" type="button" data-account-password-close>Cancelar</button>
            <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Confirmar</span></button>
        </div>
    </div>
</div>
<noscript><label class="field"><span>Senha atual para confirmar a alteração</span><input type="password" name="current_password" autocomplete="current-password"></label></noscript>
