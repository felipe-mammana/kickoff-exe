<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
echo 'Perfis existentes preservados: ' . UserPermission::migrateLegacy() . PHP_EOL;
