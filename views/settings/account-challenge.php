<section class="content-panel">
    <h1>Confirmar alteração</h1>
    <?php if ($error !== ''): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($message !== ''): ?><p role="status"><?= e($message) ?></p><?php endif; ?>
    <form class="company-form" method="post" action="/?route=account.challenge">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label class="field"><span>Método de confirmação</span><select name="method"><option value="email">Código no e-mail atual</option><?php if ($hasTotp): ?><option value="totp">Aplicativo autenticador</option><?php endif; ?></select></label>
        <label class="field"><span>Código de verificação</span><input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>
        <div class="form-actions"><button class="btn btn-muted" name="operation" value="send" formnovalidate>Enviar código por e-mail</button><button class="btn btn-primary" name="operation" value="verify">Confirmar alteração</button><button class="btn btn-muted" name="operation" value="cancel" formnovalidate>Cancelar</button></div>
    </form>
</section>
