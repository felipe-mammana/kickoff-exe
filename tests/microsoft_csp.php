<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/helpers.php';
foreach (['settings.microsoft.index', 'settings.microsoft.connect'] as $route) {
    if (form_action_policy($route) !== "form-action 'self' https://login.microsoftonline.com") {
        throw new Exception('Microsoft redirect blocked');
    }
}
foreach (['', 'login', 'settings.index', 'settings.microsoft.callback', 'settings.microsoft.test', 'settings.microsoft.disconnect'] as $route) {
    if (form_action_policy($route) !== "form-action 'self'") {
        throw new Exception('Unrelated form policy widened');
    }
}
echo "OK: 8 scoped CSP cases\n";
