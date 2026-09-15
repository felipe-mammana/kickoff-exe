<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/includes/PasswordSecurity.php';
$password = rtrim((string) stream_get_contents(STDIN), "\r\n");
if (!PasswordSecurity::valid($password)) exit(PasswordSecurity::REQUIREMENTS . "\n");
echo PasswordSecurity::hash($password) . "\n";
