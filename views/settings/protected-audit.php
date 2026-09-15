<section class="content-panel">
    <h1>Arquivos de auditoria</h1>
    <form class="company-form" method="post" action="/?route=settings.auditFiles">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label class="field"><span>Data (UTC)</span><input type="date" name="date" value="<?= e($date) ?>" required></label>
        <label class="field"><span>Senha atual</span><input type="password" name="password" autocomplete="current-password" required></label>
        <?php if ($error !== ''): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
        <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('unlock') ?>Consultar</button></div>
    </form>
    <?php if ($text !== null): ?><pre style="white-space:pre-wrap;overflow-wrap:anywhere;max-height:60vh;overflow:auto"><?= e($text) ?></pre><?php endif; ?>
</section>
