<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

db()->exec('ALTER TABLE machines MODIFY machine_password TEXT NULL');
db()->exec('ALTER TABLE machines MODIFY admin_password TEXT NULL');

$rows = db()->query('SELECT id, machine_password, admin_password FROM machines')->fetchAll();
$stmt = db()->prepare(
    'UPDATE machines
     SET machine_password = :machine_password,
         admin_password = :admin_password
     WHERE id = :id'
);

$updated = 0;
foreach ($rows as $row) {
    $machinePassword = $row['machine_password'] ?? null;
    $adminPassword = $row['admin_password'] ?? null;
    $encryptedMachinePassword = CredentialCrypto::encrypt($machinePassword);
    $encryptedAdminPassword = CredentialCrypto::encrypt($adminPassword);

    if ($encryptedMachinePassword !== $machinePassword || $encryptedAdminPassword !== $adminPassword) {
        $stmt->execute([
            'id' => (int) $row['id'],
            'machine_password' => $encryptedMachinePassword,
            'admin_password' => $encryptedAdminPassword,
        ]);
        $updated++;
    }
}

echo 'Migracao de credenciais concluida.' . PHP_EOL;
echo 'Dispositivos atualizados: ' . $updated . PHP_EOL;
