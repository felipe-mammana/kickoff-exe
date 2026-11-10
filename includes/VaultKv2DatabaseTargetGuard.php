<?php

declare(strict_types=1);

final class VaultKv2DatabaseTargetGuard
{
    /** @return array{id: string, name: string, hostname: string} */
    public static function inspectDockerTarget(string $identifier, string $host, string $inspectMode): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{0,127}$/D', $identifier) !== 1) {
            throw new RuntimeException('Identificador de container Docker invalido.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('No modo Docker, DB_HOST deve ser um IP do container inspecionado.');
        }

        $command = match ($inspectMode) {
            'direct' => ['docker', 'inspect', '--type', 'container', $identifier],
            'sudo-n' => ['/usr/bin/sudo', '-n', '/usr/bin/docker', 'inspect', '--type', 'container', $identifier],
            default => throw new RuntimeException('VAULT_KV2_TEST_DOCKER_INSPECT deve ser direct ou sudo-n.'),
        };
        $process = @proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Nao foi possivel executar docker inspect.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + 5.0;
        $status = null;

        try {
            do {
                $stdout .= (string) stream_get_contents($pipes[1], 1048576 - strlen($stdout));
                $stderr .= (string) stream_get_contents($pipes[2], 65536 - strlen($stderr));
                if (strlen($stdout) >= 1048576 || strlen($stderr) >= 65536) {
                    proc_terminate($process);
                    throw new RuntimeException('Saida de docker inspect excedeu o limite permitido.');
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new RuntimeException('docker inspect excedeu o tempo limite.');
                }
                usleep(20000);
            } while (true);

            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closeCode = proc_close($process);
        }

        $exitCode = is_array($status) && is_int($status['exitcode']) && $status['exitcode'] >= 0
            ? $status['exitcode']
            : $closeCode;
        if ($exitCode !== 0) {
            throw new RuntimeException('docker inspect nao confirmou o container descartavel informado.');
        }

        try {
            $decoded = json_decode($stdout, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Resposta invalida de docker inspect.', 0, $error);
        }
        if (!is_array($decoded) || count($decoded) !== 1 || !is_array($decoded[0])) {
            throw new RuntimeException('docker inspect nao retornou exatamente um container.');
        }

        $container = $decoded[0];
        $containerId = strtolower((string) ($container['Id'] ?? ''));
        $containerName = ltrim((string) ($container['Name'] ?? ''), '/');
        $expected = strtolower($identifier);
        $identifierMatches = hash_equals(strtolower($containerName), $expected)
            || (strlen($expected) >= 12 && str_starts_with($containerId, $expected));
        if (preg_match('/^[0-9a-f]{64}$/D', $containerId) !== 1 || !$identifierMatches) {
            throw new RuntimeException('A identidade Docker nao corresponde ao identificador esperado.');
        }
        if (($container['State']['Running'] ?? false) !== true) {
            throw new RuntimeException('O container Docker autorizado nao esta em execucao.');
        }

        $configuredAddress = inet_pton($host);
        $addressMatches = false;
        foreach ((array) ($container['NetworkSettings']['Networks'] ?? []) as $network) {
            if (!is_array($network)) {
                continue;
            }
            foreach (['IPAddress', 'GlobalIPv6Address'] as $field) {
                $candidate = (string) ($network[$field] ?? '');
                if ($candidate !== '' && inet_pton($candidate) === $configuredAddress) {
                    $addressMatches = true;
                    break 2;
                }
            }
        }
        if (!$addressMatches) {
            throw new RuntimeException('DB_HOST nao pertence ao container Docker autorizado.');
        }

        $hostname = (string) ($container['Config']['Hostname'] ?? '');
        if ($hostname === '' || strlen($hostname) > 255) {
            throw new RuntimeException('Hostname interno do container Docker invalido.');
        }

        return ['id' => $containerId, 'name' => $containerName, 'hostname' => $hostname];
    }

    /** @param array{id: string, name: string, hostname: string}|null $dockerTarget */
    public static function assertConnection(PDO $pdo, ?array $dockerTarget, ?string $expectedDatabase): void
    {
        $identity = $pdo->query(
            'SELECT @@hostname AS server_hostname, DATABASE() AS selected_database'
        )->fetch();
        if (!is_array($identity) || $identity['selected_database'] !== $expectedDatabase) {
            throw new RuntimeException('A conexao PDO nao aponta para o banco temporario esperado.');
        }
        if ($dockerTarget !== null
            && !hash_equals((string) $dockerTarget['hostname'], (string) ($identity['server_hostname'] ?? ''))
        ) {
            throw new RuntimeException('O MySQL conectado nao corresponde ao container inspecionado.');
        }
    }

    public static function assertMigrationTarget(PDO $pdo): void
    {
        if (APP_ENV === 'homologacao') {
            if (DB_NAME !== 'kickoff_hml') {
                throw new RuntimeException('Alvo recusado: homologacao exige o banco kickoff_hml.');
            }
            self::assertConnection($pdo, null, 'kickoff_hml');
            return;
        }

        if (APP_ENV !== 'testing'
            || getenv('VAULT_KV2_TEST_DISPOSABLE') !== 'YES_I_UNDERSTAND'
            || getenv('VAULT_KV2_DISPOSABLE_TEST') !== '1'
            || preg_match('/^kickoff_vault_kv2_test_[0-9a-f]{16}$/D', DB_NAME) !== 1
        ) {
            throw new RuntimeException('Alvo descartavel de teste nao autorizado.');
        }

        $mode = strtolower(trim((string) (getenv('VAULT_KV2_TEST_TARGET') ?: 'loopback')));
        $dockerTarget = null;
        if ($mode === 'loopback') {
            if (!in_array(strtolower(DB_HOST), ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new RuntimeException('Alvo loopback de teste invalido.');
            }
        } elseif ($mode === 'docker') {
            $identifier = trim((string) getenv('VAULT_KV2_TEST_CONTAINER'));
            if ($identifier === '') {
                throw new RuntimeException('Container Docker esperado nao informado.');
            }
            $inspectMode = strtolower(trim((string) (getenv('VAULT_KV2_TEST_DOCKER_INSPECT') ?: 'direct')));
            $dockerTarget = self::inspectDockerTarget($identifier, DB_HOST, $inspectMode);
        } else {
            throw new RuntimeException('Modo de destino de teste invalido.');
        }

        self::assertConnection($pdo, $dockerTarget, DB_NAME);
    }
}
