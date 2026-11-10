<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

throw new RuntimeException(
    'Migracao legada do cofre descontinuada. Use database/apply_vault_kv2_migration.php na instalacao limpa aprovada.'
);
