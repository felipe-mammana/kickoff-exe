<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$path = dirname(__DIR__) . '/config/crypto.local.php';
if (file_exists($path)) { fwrite(STDERR, "Configuracao existente preservada.\n"); exit(1); }
$rings = [];
foreach (['credentials', 'totp', 'microsoft'] as $purpose) {
    $rings[$purpose] = ['active' => 'key1', 'keys' => ['key1' => base64_encode(random_bytes(32))]];
}
$handle = fopen($path, 'x');
if (!$handle) exit(1);
chmod($path, 0600);
$content = "<?php\nreturn " . var_export($rings, true) . ";\n";
if (fwrite($handle, $content) !== strlen($content)) {
    fclose($handle);
    fwrite(STDERR, "Escrita incompleta; nao iniciar o sistema antes de revisar.\n");
    exit(1);
}
fclose($handle);
echo "Chaves criadas sem exibir valores. Restrinja ACL no Windows e preserve copia segura.\n";
