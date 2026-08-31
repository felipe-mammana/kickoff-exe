<section class="login-panel">
    <div class="login-copy">
        <?= brand_logo('brand-logo login-hero-logo') ?>
        <h1>Controle corporativo de dispositivos e evidências.</h1>
        <p>Cadastre empresas, equipamentos, fotos e histórico operacional em uma interface única.</p>
        <div class="login-metrics">
            <span>Auditoria ativa</span>
            <span>Upload mobile</span>
            <span>Modo escuro</span>
        </div>
    </div>

    <div class="auth-card-stack">
        <form class="auth-card" action="/?route=login" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="auth-card-head">
                <div>
                    <h2><?= !empty($requiresTwoFactor) ? 'Verificação 2FA' : 'Acessar sistema' ?></h2>
                    <p><?= !empty($requiresTwoFactor) ? 'Informe o código do seu aplicativo autenticador.' : 'Entre para continuar o inventário.' ?></p>
                </div>
            </div>

            <?php if (!empty($requiresTwoFactor)): ?>
                <?php if (!empty($twoFactorUserEmail)): ?>
                    <p class="field-hint">Conta: <strong><?= e($twoFactorUserEmail) ?></strong></p>
                <?php endif; ?>
                <label class="field">
                    <span>Código 2FA</span>
                    <input type="text" name="two_factor_code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
                    <small><?= !empty($emailCodeSent) ? 'Use o código do aplicativo autenticador ou o código enviado por e-mail.' : 'Use o aplicativo autenticador ou solicite um código por e-mail.' ?></small>
                </label>
            <?php else: ?>
                <label class="field">
                    <span>E-mail</span>
                    <input type="email" name="email" autocomplete="email" required autofocus>
                </label>

                <label class="field password-field">
                    <span>Senha</span>
                    <span class="password-wrap">
                        <input type="password" name="password" autocomplete="current-password" required data-password-input>
                        <button class="password-toggle-icon" type="button" data-password-toggle aria-label="Mostrar senha" title="Mostrar senha">
                            <?= icon('eye') ?>
                        </button>
                    </span>
                </label>
            <?php endif; ?>

            <button class="btn btn-primary btn-full" type="submit"><?= icon('log-out') ?><span><?= !empty($requiresTwoFactor) ? 'Validar código' : 'Entrar' ?></span></button>
        </form>

        <?php if (!empty($requiresTwoFactor)): ?>
            <form class="auth-secondary-actions" action="/?route=login.2fa.email" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-muted btn-full" type="submit"><?= icon('mail') ?><span>Enviar código por e-mail</span></button>
            </form>
            <form class="auth-secondary-actions" action="/?route=login.2fa.cancel" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-muted btn-full" type="submit"><?= icon('x') ?><span>Cancelar e voltar</span></button>
            </form>
        <?php endif; ?>
    </div>
</section>
