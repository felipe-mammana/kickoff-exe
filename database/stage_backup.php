<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (count($argv) !== 3) exit("Uso: php database/stage_backup.php entrada.exe-zip pasta-nova\n");
$hashes = BackupRecovery::stage((string) file_get_contents($argv[1]), $argv[2]);
echo count($hashes) . " arquivos recuperados e conferidos. A pasta contem dados em claro; proteja-a.\n";
