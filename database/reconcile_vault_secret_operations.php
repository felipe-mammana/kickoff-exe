<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (APP_ENV !== 'homologacao' || DB_NAME !== 'kickoff_hml') {
    throw new RuntimeException('Reconciliacao recusada: use APP_ENV=homologacao e o banco kickoff_hml.');
}

$limit = isset($argv[1]) ? (int) $argv[1] : 50;
$result = VaultCredentialSecretService::fromApplication()->reconcilePending($limit);

echo 'Concluidas: ' . $result['completed'] . PHP_EOL;
echo 'Falhas definitivas: ' . $result['failed'] . PHP_EOL;
echo 'Ainda pendentes: ' . $result['pending'] . PHP_EOL;
echo 'Falhas de auditoria: ' . $result['audit_failed'] . PHP_EOL;

if ($result['pending'] > 0 || $result['audit_failed'] > 0) {
    exit(2);
}
