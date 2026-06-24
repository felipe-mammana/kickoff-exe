<?php
$route = (string) ($_GET['route'] ?? 'dashboard');
$isAudit = $route === 'audit.index';
$isMachine = strpos($route, 'machines.') === 0;
$isCompany = strpos($route, 'companies.') === 0;
$isUsers = strpos($route, 'users.') === 0;
$isSettings = strpos($route, 'settings.') === 0;
$companyIdForNav = (int) ($_GET['company_id'] ?? ($company['id'] ?? ($machine['company_id'] ?? 0)));
?>
<!doctype html>
<html lang="pt-BR" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($title ?? 'Dashboard') . ' - ' . APP_NAME) ?></title>
    <link rel="icon" type="image/webp" href="/assets/brand/exe-icon.webp">
    <script>
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
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="is-loading">
    <div class="app-loader" data-app-loader aria-hidden="true">
        <div class="app-loader-brand">
            <?= brand_logo('brand-logo loader-logo') ?>
            <?= brand_logo('brand-icon loader-icon', true) ?>
        </div>
    </div>

    <?php if (current_user()): ?>
        <div class="app-frame">
            <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>

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
        </div>
    <?php else: ?>
        <main class="auth-shell">
            <?php foreach (consume_flash() as $message): ?>
                <div class="alert alert-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
            <?php endforeach; ?>

            <?php require $viewFile; ?>
        </main>
    <?php endif; ?>

    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/export.js"></script>
</body>
</html>
