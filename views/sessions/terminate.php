<dialog class="session-termination-modal" open aria-labelledby="session-termination-title">
    <header class="session-termination-head"><h2 id="session-termination-title">Encerrar sessão</h2><button type="button" class="icon-btn" data-termination-close aria-label="Fechar"><?= icon('x') ?></button></header>
    <p><?= e($targetName) ?></p>
    <form class="company-form" action="/?route=sessions.terminate" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="confirm_termination" value="1">
        <label class="field"><span>Senha administrativa de sessões</span><input type="password" name="admin_password" autocomplete="off" required autofocus></label>
        <?php if ($error !== ''): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
        <div class="form-actions"><a class="btn btn-muted" href="/?route=sessions.index" data-termination-close>Cancelar</a><button class="btn btn-danger" type="submit"><?= icon('log-out') ?>Encerrar sessão</button></div>
    </form>
</dialog>
