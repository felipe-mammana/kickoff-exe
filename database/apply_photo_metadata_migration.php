<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

function photo_column_exists(string $column): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $stmt->execute([
        'table_name' => 'machine_photos',
        'column_name' => $column,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

if (!photo_column_exists('photo_topic')) {
    db()->exec("ALTER TABLE machine_photos ADD COLUMN photo_topic VARCHAR(40) NOT NULL DEFAULT 'equipamento' AFTER photo_type");
    echo 'Coluna photo_topic adicionada.' . PHP_EOL;
}

if (!photo_column_exists('location_name')) {
    db()->exec('ALTER TABLE machine_photos ADD COLUMN location_name VARCHAR(160) NULL AFTER photo_topic');
    echo 'Coluna location_name adicionada.' . PHP_EOL;
}

echo 'Migracao de metadados de fotos concluida.' . PHP_EOL;
