<?php
declare(strict_types=1);
require dirname(__DIR__) . '/controllers/MicrosoftMailController.php';
final class AccessDeniedForTest extends RuntimeException {}
function require_admin(): void { throw new AccessDeniedForTest(); }
foreach (['index', 'connect', 'callback', 'test', 'disconnect'] as $action) {
    try {
        MicrosoftMailController::dispatch($action);
        throw new Exception('Action executed without administrator authorization');
    } catch (AccessDeniedForTest $e) {
        echo "OK: admin guard for $action\n";
    }
}
