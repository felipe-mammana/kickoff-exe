<?php
declare(strict_types=1);
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/includes/CodeEmailTemplate.php';
$html = CodeEmailTemplate::render('Acesso <teste>', '123456', 'Confirme seu acesso.', 'Válido por 10 minutos.');
foreach (['123456', 'Não compartilhe', 'cid:exe-logo', 'Acesso &lt;teste&gt;', 'max-width:560px'] as $expected) {
    if (!str_contains($html, $expected)) { throw new Exception('Missing template element'); }
}
if (!str_starts_with(CodeEmailTemplate::logo(), "\x89PNG")) { throw new Exception('Invalid PNG logo'); }
echo "OK: template, escaping, warning and embedded PNG\n";
