<section class="asset-page-head">
    <h1>Email Microsoft</h1>
    <a class="btn btn-muted" href="/?route=settings.index">Voltar</a>
</section>
<section>
    <p>Remetente: <strong><?= e(MicrosoftMail::config()['sender'] ?? '') ?></strong></p>
    <p><?= MicrosoftMail::connected() ? 'Conta conectada' : 'Conta desconectada' ?></p>
    <?php if (!MicrosoftMail::ready()): ?>
        <p role="status">Configuracao local pendente.</p>
    <?php else: ?>
        <div class="header-actions">
        <?php foreach (['connect' => 'Conectar Microsoft', 'test' => 'Enviar teste', 'disconnect' => 'Desconectar'] as $action => $label): ?>
            <form method="post" action="/?route=settings.microsoft.<?= e($action) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-muted" type="submit"><?= icon($action === 'test' ? 'mail' : 'settings') ?><?= e($label) ?></button>
            </form>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
