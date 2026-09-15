<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/includes/bootstrap.php';
SessionPresence::ensureTable();
echo "Tabela de presenca preparada.\n";
