<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
if ($argc !== 3 || !is_file($argv[1]) || file_exists($argv[2])) {
    fwrite(STDERR, "Uso: php scripts/decrypt_vault_export.php entrada.exe-vault novo-arquivo.json\nA saida contem senhas em claro; proteja e remova apos o uso.\n");
    exit(1);
}
$plain = EncryptedBackup::decrypt((string) file_get_contents($argv[1]), 'vault');
$handle = fopen($argv[2], 'x');
if (!$handle) throw new RuntimeException('Nao foi possivel criar a saida.');
try { if (fwrite($handle, $plain) !== strlen($plain)) throw new RuntimeException('Escrita incompleta.'); }
finally { fclose($handle); }
echo "Exportacao decifrada. Proteja o arquivo de saida.\n";
