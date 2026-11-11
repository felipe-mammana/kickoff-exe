<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/bootstrap.php';

(static function (array $arguments): void {
    $apply = in_array('--apply', $arguments, true);
    $confirmed = in_array('--confirm-hml=kickoff_hml', $arguments, true);

    if (in_array('--help', $arguments, true)) {
        echo "Uso:\n";
        echo "  php database/seed_vault_categories.php\n";
        echo "  php database/seed_vault_categories.php --apply --confirm-hml=kickoff_hml\n";
        return;
    }

    if (APP_ENV !== 'homologacao' || DB_NAME !== 'kickoff_hml') {
        throw new RuntimeException('Seeder recusado: exige APP_ENV=homologacao e DB_NAME=kickoff_hml.');
    }

    $pdo = db();
    $connectedDatabase = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($connectedDatabase !== 'kickoff_hml') {
        throw new RuntimeException('Seeder recusado: a conexão PDO não aponta para kickoff_hml.');
    }

    $categories = VaultCategorySeeder::defaultCategories();
    $seeder = new VaultCategorySeeder($pdo);

    if (!$apply) {
        echo "Simulação somente leitura. Nenhum dado será alterado.\n";
        foreach ($seeder->inspect($categories) as $item) {
            echo sprintf("[%s] %s\n", strtoupper((string) $item['status']), (string) $item['name']);
        }
        echo "Para aplicar: --apply --confirm-hml=kickoff_hml\n";
        return;
    }

    if (!$confirmed) {
        throw new RuntimeException('Aplicação recusada: informe --confirm-hml=kickoff_hml.');
    }

    $result = $seeder->synchronize($categories);
    echo sprintf(
        "Categorias padrão sincronizadas: %d criada(s), %d atualizada(s), %d inalterada(s).\n",
        $result['created'],
        $result['updated'],
        $result['unchanged']
    );
})(array_slice($argv, 1));
