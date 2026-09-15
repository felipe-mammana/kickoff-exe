<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (count($argv) !== 4 || !in_array($argv[3], ['sql', 'zip'], true)) exit("Uso: php database/decrypt_backup.php entrada saida sql|zip\n");
$plain = EncryptedBackup::decrypt((string) file_get_contents($argv[1]), $argv[3]);
$file = fopen($argv[2], 'x');
if (!$file) throw new RuntimeException('Destino deve ser novo.');
try {
    if (fwrite($file, $plain) !== strlen($plain)) throw new RuntimeException('Falha de escrita.');
} finally { fclose($file); }
echo "Backup descriptografado. Proteja e remova a copia em claro apos uso.\n";
