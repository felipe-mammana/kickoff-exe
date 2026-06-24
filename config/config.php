<?php

declare(strict_types=1);

define('APP_NAME', 'EXE');
define('APP_URL', 'http://localhost:8000');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/public/uploads');
define('UPLOAD_URL', '/uploads');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'inventario_ti');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_MIMES', ['image/jpeg', 'image/png', 'image/webp']);
