<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$stmt = db()->prepare(
    'INSERT INTO users (name, email, password_hash, is_admin)
     VALUES (:name, :email, :password_hash, 1)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), is_admin = 1'
);

$users = [
    ['name' => 'Administrador', 'email' => 'admin@empresa.com', 'password' => 'admin123'],
    ['name' => 'Felipe Mammana', 'email' => 'felipe.mammana@exesolcuoes.com.br', 'password' => 'exe@123'],
];

foreach ($users as $user) {
    $stmt->execute([
        'name' => $user['name'],
        'email' => $user['email'],
        'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
    ]);

    echo "Usuario inicial pronto: {$user['email']} / {$user['password']}" . PHP_EOL;
}
