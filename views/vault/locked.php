<section class="content-panel">
    <h1>Cofre bloqueado</h1>
    <form method="post" action="/?route=vault.index" class="company-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="vault_unlock" value="1">
        <label class="field"><span>Senha atual</span><input type="password" name="password" autocomplete="current-password" required autofocus></label>
        <?php if ($error !== ''): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('unlock') ?>Desbloquear cofre</button></div>
    </form>
</section>
