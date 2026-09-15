<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/includes/bootstrap.php';
$args = array_slice($argv, 1);
foreach ($args as $arg) {
    if (!in_array($arg, ['--apply', '--maintenance-confirmed'], true)) {
        fwrite(STDERR, "Uso: php database/rotate_crypto.php [--apply --maintenance-confirmed]\n");
        exit(1);
    }
}
$apply = in_array('--apply', $args, true);
if ($apply && !in_array('--maintenance-confirmed', $args, true)) {
    fwrite(STDERR, "Pare os processos web/workers e confirme backup antes de aplicar.\n");
    exit(1);
}
try {
    $counts = CryptoRotation::run(db(), $apply);
    foreach ($counts as $target => $count) echo $target . ': ' . $count . PHP_EOL;
    echo $apply ? "Migracao confirmada. Preserve chaves antigas para backups.\n" : "Simulacao concluida; nenhum registro alterado.\n";
} catch (Throwable $e) {
    // Do not expose SQL errors, row values or secret material in CLI logs.
    fwrite(STDERR, "Migracao cancelada; nenhuma alteracao confirmada. Verifique schema, chaves e integridade dos dados.\n");
    exit(1);
}
