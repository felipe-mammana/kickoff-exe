<?php
$route = (string) ($_GET['route'] ?? 'dashboard');
$isAudit = $route === 'audit.index';
$isMachine = strpos($route, 'machines.') === 0;
$isCompany = strpos($route, 'companies.') === 0;
$isUsers = strpos($route, 'users.') === 0;
$isSettings = strpos($route, 'settings.') === 0;
$isVault = strpos($route, 'vault.') === 0;
$companyIdForNav = (int) ($_GET['company_id'] ?? ($company['id'] ?? ($machine['company_id'] ?? 0)));
$assetVersion = static function (string $path): string {
    $file = BASE_PATH . '/public' . $path;

    return is_file($file) ? (string) filemtime($file) : '1';
};
?>
<!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Dashboard') . ' - ' . APP_NAME) ?></title>
    <link rel="icon" type="image/webp" href="/assets/brand/exe-icon.webp">
    <script nonce="<?= e(csp_nonce()) ?>">
        (function () {
            var theme = 'light';
            try {
                theme = localStorage.getItem('theme') || theme;
            } catch (error) {
                theme = 'light';
            }
            document.documentElement.setAttribute('data-theme', theme === 'dark' ? 'dark' : 'light');
        })();
    </script>
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e($assetVersion('/assets/css/app.css')) ?>">
</head>
<body class="<?= $isVault ? 'route-vault' : '' ?>">
    <?php if (current_user()): ?>
        <div class="app-frame">
            <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
            <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Fechar menu"></button>

            <div class="app-main">
                <?php require BASE_PATH . '/views/partials/topbar.php'; ?>

                <main class="app-shell">
                    <?php foreach (consume_flash() as $message): ?>
                        <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
                    <?php endforeach; ?>

                    <?php require $viewFile; ?>
                </main>
            </div>

            <?php require BASE_PATH . '/views/partials/mobile-nav.php'; ?>

            <div class="company-modal confirm-modal" data-confirm-modal hidden>
                <div class="company-modal-dialog confirm-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
                    <header class="modal-head">
                        <div>
                            <span class="eyebrow">Confirmação</span>
                            <h2 id="confirm-modal-title">Confirmar ação</h2>
                        </div>
                        <button class="icon-btn" type="button" data-confirm-cancel aria-label="Fechar"><?= icon('x') ?></button>
                    </header>
                    <div class="confirm-modal-body">
                        <p data-confirm-message>Deseja continuar?</p>
                    </div>
                    <footer class="form-actions confirm-modal-actions">
                        <button class="btn btn-muted" type="button" data-confirm-cancel>Cancelar</button>
                        <button class="btn btn-danger" type="button" data-confirm-submit>
                            <span data-confirm-icon><?= icon('trash-2') ?></span>
                            <span>Confirmar</span>
                        </button>
                    </footer>
                </div>
            </div>
        </div>
    <?php else: ?>
        <main class="auth-shell">
            <?php foreach (consume_flash() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>

            <?php require $viewFile; ?>
        </main>
    <?php endif; ?>

    <script src="/assets/js/app.js?v=<?= e($assetVersion('/assets/js/app.js')) ?>"></script>
    <script src="/assets/js/export.js?v=<?= e($assetVersion('/assets/js/export.js')) ?>"></script>
</body>
</html>
