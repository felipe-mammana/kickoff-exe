<?php

declare(strict_types=1);

return [
    'SESSION_ADMIN_PASSWORD_HASH' => '$argon2id$v=19$m=65536,t=3,p=1$b3QuVFpOYjZJSjhZcW5kMg$vY78S/crqCTZp/4t30Xo2ca1g5bqmW35R9vBuKrWAE0', // Hash da senha exclusiva para encerrar sessoes.
    'APP_NAME' => 'EXE',
    'APP_ENV' => 'local',
    'APP_DEBUG' => true,
    'APP_URL' => 'http://localhost:8000',
    'APP_KEY' => 'gere-uma-chave-com-database/generate_app_key.php',
    'MAIL_FROM' => 'contatofelipewmammana@gmail.com',
    'MAIL_FROM_NAME' => 'EXE',
    'SMTP_HOST' => 'smtp.gmail.com',
    'SMTP_PORT' => 587,
    'SMTP_USERNAME' => 'contatofelipewmammana@gmail.com',
    'SMTP_PASSWORD' => 'senha-de-app-do-google',
    'SMTP_ENCRYPTION' => 'tls',

    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'inventario_ti',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4',

    'API_MAX_JSON_BYTES' => 1048576,
    'API_RATE_LIMIT_WINDOW_SECONDS' => 60,
    'API_RATE_LIMIT_PUBLIC_MAX_REQUESTS' => 60,
    'API_RATE_LIMIT_AUTH_MAX_REQUESTS' => 120,

    'ADMIN_NAME' => 'Administrador',
    'ADMIN_EMAIL' => 'admin@empresa.com',
    'ADMIN_PASSWORD' => 'troque-esta-senha',
];
