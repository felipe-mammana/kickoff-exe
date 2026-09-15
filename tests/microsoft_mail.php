<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/includes/MicrosoftMail.php';
function current_user(): array { return ['id' => 7]; }
foreach ([
    'http://localhost:8080/?route=settings.microsoft.callback' => true,
    'https://example.com/?route=settings.microsoft.callback' => true,
    'http://example.com/?route=settings.microsoft.callback' => false,
    'http://localhost.example.com/' => false,
    'http://localhost@evil.example/' => false,
    'https://user:password@example.com/' => false,
    'https://example.com/#callback' => false,
    'localhost8080' => false,
] as $uri => $expected) {
    if (MicrosoftMail::validRedirectUri($uri) !== $expected) {
        throw new Exception('Redirect URI validation failed');
    }
}
echo "OK: 8 redirect URI cases\n";
$_SESSION = [];
$_GET = ['state' => 'invalid', 'code' => 'fake'];
try {
    MicrosoftMail::callback();
    throw new Exception('Invalid state accepted');
} catch (RuntimeException $e) {
    echo "OK: missing state rejected\n";
}
$_SESSION['microsoft_oauth'] = ['state' => 'expected', 'expires' => time() - 1, 'user_id' => 7];
$_GET['state'] = 'expected';
try {
    MicrosoftMail::callback();
    throw new Exception('Expired state accepted');
} catch (RuntimeException $e) {
    if (isset($_SESSION['microsoft_oauth'])) { throw new Exception('State not consumed'); }
    echo "OK: expired state rejected and consumed\n";
}
