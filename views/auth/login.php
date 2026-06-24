<section class="login-panel">
    <div class="login-copy">
        <?= brand_logo('brand-logo login-hero-logo') ?>
        <h1>Controle corporativo de dispositivos e evidencias.</h1>
        <p>Cadastre empresas, equipamentos, fotos e historico operacional em uma interface unica.</p>
        <div class="login-metrics">
            <span>Auditoria ativa</span>
            <span>Upload mobile</span>
            <span>Modo escuro</span>
        </div>
    </div>

    <form class="auth-card" action="/?route=login" method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <div class="auth-card-head">
            <div>
                <h2>Acessar sistema</h2>
                <p>Entre para continuar o inventario.</p>
            </div>
        </div>

        <label class="field">
            <span>E-mail</span>
            <input type="email" name="email" autocomplete="email" required autofocus>
        </label>

        <label class="field password-field">
            <span>Senha</span>
            <span class="password-wrap">
                <input type="password" name="password" autocomplete="current-password" required data-password-input>
                <button type="button" data-password-toggle>Ver</button>
            </span>
        </label>

        <button class="btn btn-primary btn-full" type="submit"><?= icon('log-out') ?><span>Entrar</span></button>
    </form>
</section>
