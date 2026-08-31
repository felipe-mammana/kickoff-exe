<section class="empty-state">
    <h1>Algo não saiu como esperado</h1>
    <p>O sistema registrou o erro. Tente novamente em instantes.</p>
    <?php if (APP_DEBUG && isset($exception) && $exception instanceof Throwable): ?>
        <pre class="debug-box"><?= e($exception->getMessage()) . "\n" . e($exception->getFile() . ':' . $exception->getLine()) ?></pre>
    <?php endif; ?>
    <a class="btn btn-primary" href="/">Voltar ao inicio</a>
</section>
