<?php

declare(strict_types=1);

$localConfig = [];
$localConfigFile = __DIR__ . '/local.php';

if (is_file($localConfigFile)) {
    $loadedConfig = require $localConfigFile;
    $localConfig = is_array($loadedConfig) ? $loadedConfig : [];
}

function config_value(string $key, $default = null)
{
    global $localConfig;

    $envValue = getenv($key);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }

    return array_key_exists($key, $localConfig) ? $localConfig[$key] : $default;
}

function config_bool(string $key, bool $default = false): bool
{
    $value = config_value($key, $default ? 'true' : 'false');

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

define('APP_NAME', (string) config_value('APP_NAME', 'EXE'));
define('APP_ENV', (string) config_value('APP_ENV', 'local'));
define('APP_DEBUG', config_bool('APP_DEBUG', APP_ENV === 'local'));
define('APP_URL', rtrim((string) config_value('APP_URL', 'http://localhost:8000'), '/'));
define('BASE_PATH', dirname(__DIR__));
define('STORAGE_PATH', BASE_PATH . '/storage');
define('UPLOAD_PATH', is_dir(BASE_PATH . '/public') ? BASE_PATH . '/public/uploads' : BASE_PATH . '/uploads');
define('UPLOAD_URL', '/uploads');

define('DB_HOST', (string) config_value('DB_HOST', '127.0.0.1'));
define('DB_NAME', (string) config_value('DB_NAME', 'inventario_ti'));
define('DB_USER', (string) config_value('DB_USER', 'root'));
define('DB_PASS', (string) config_value('DB_PASS', ''));
define('DB_CHARSET', (string) config_value('DB_CHARSET', 'utf8mb4'));

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp']);
