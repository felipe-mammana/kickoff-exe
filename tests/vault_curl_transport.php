<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/VaultHttpTransport.php';
require_once dirname(__DIR__) . '/includes/VaultOperationException.php';
require_once dirname(__DIR__) . '/includes/CurlVaultHttpTransport.php';

if (!extension_loaded('curl') || !extension_loaded('openssl') || !function_exists('proc_open')) {
    fwrite(STDERR, "SKIP: cURL, OpenSSL e proc_open são necessários para os testes locais do transporte.\n");
    exit(77);
}

// Never route this loopback-only fixture through a machine proxy.
putenv('NO_PROXY=127.0.0.1,localhost');
putenv('no_proxy=127.0.0.1,localhost');

if (($argv[1] ?? '') === '--server') {
    vault_test_server(array_slice($argv, 2));
    exit(0);
}

$checks = 0;
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kickoff-vault-curl-' . bin2hex(random_bytes(8));
if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
    throw new RuntimeException('Não foi possível criar o diretório temporário de testes.');
}

function vault_test_check(string $label, bool $condition): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('Falhou: ' . $label);
    }
    $checks++;
}

function vault_test_key(string $configPath): OpenSSLAsymmetricKey
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
        'config' => $configPath,
    ]);
    if (!$key instanceof OpenSSLAsymmetricKey) {
        throw new RuntimeException('Falha ao gerar chave temporária de teste.');
    }
    return $key;
}

/** @return array{OpenSSLAsymmetricKey, OpenSSLCertificate} */
function vault_test_ca(string $name, string $configPath): array
{
    $key = vault_test_key($configPath);
    $csr = openssl_csr_new(['commonName' => $name], $key, [
        'digest_alg' => 'sha256',
        'config' => $configPath,
        'req_extensions' => 'v3_req',
    ]);
    if ($csr === false) {
        throw new RuntimeException('Falha ao criar CSR da CA de teste.');
    }
    $certificate = openssl_csr_sign($csr, null, $key, 2, [
        'digest_alg' => 'sha256',
        'config' => $configPath,
        'x509_extensions' => 'v3_ca',
    ]);
    if (!$certificate instanceof OpenSSLCertificate) {
        throw new RuntimeException('Falha ao assinar CA de teste.');
    }
    return [$key, $certificate];
}

/** @return array{string, string} Paths to PEM certificate and private key. */
function vault_test_server_certificate(
    string $dir,
    string $name,
    OpenSSLCertificate $ca,
    OpenSSLAsymmetricKey $caKey,
    string $configPath,
    string $extensionSection
): array {
    $key = vault_test_key($configPath);
    $csr = openssl_csr_new(['commonName' => $name], $key, [
        'digest_alg' => 'sha256',
        'config' => $configPath,
        'req_extensions' => 'v3_req',
    ]);
    if ($csr === false) {
        throw new RuntimeException('Falha ao criar CSR do servidor TLS de teste.');
    }
    $certificate = openssl_csr_sign($csr, $ca, $caKey, 2, [
        'digest_alg' => 'sha256',
        'config' => $configPath,
        'x509_extensions' => $extensionSection,
    ]);
    if (!$certificate instanceof OpenSSLCertificate || !openssl_x509_export($certificate, $certificatePem)) {
        throw new RuntimeException('Falha ao emitir certificado TLS temporário.');
    }
    if (!openssl_pkey_export($key, $keyPem, null, ['config' => $configPath])) {
        throw new RuntimeException('Falha ao exportar chave TLS temporária.');
    }

    $certPath = $dir . DIRECTORY_SEPARATOR . $name . '.crt';
    $keyPath = $dir . DIRECTORY_SEPARATOR . $name . '.key';
    if (file_put_contents($certPath, $certificatePem, LOCK_EX) === false
        || file_put_contents($keyPath, $keyPem, LOCK_EX) === false) {
        throw new RuntimeException('Falha ao salvar material TLS temporário.');
    }
    return [$certPath, $keyPath];
}

/** @return array{resource, array<int, resource>, int} */
function vault_test_start_server(string $mode, string $certPath, string $keyPath): array
{
    $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
    if ($reservation === false) {
        throw new RuntimeException('Não foi possível reservar porta local de teste.');
    }
    $address = stream_socket_get_name($reservation, false);
    fclose($reservation);
    if (!is_string($address) || !preg_match('/:(\d+)$/', $address, $match)) {
        throw new RuntimeException('Não foi possível determinar a porta local de teste.');
    }
    $port = (int) $match[1];

    $pipes = [];
    $process = proc_open(
        [PHP_BINARY, __FILE__, '--server', (string) $port, $mode, $certPath, $keyPath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Não foi possível iniciar o servidor TLS local de teste.');
    }

    stream_set_blocking($pipes[1], false);
    $ready = '';
    $deadline = microtime(true) + 5.0;
    while (microtime(true) < $deadline && !str_contains($ready, "READY\n")) {
        $ready .= (string) fread($pipes[1], 1024);
        if (feof($pipes[1])) {
            break;
        }
        usleep(10000);
    }
    if (!str_contains($ready, "READY\n")) {
        proc_terminate($process);
        throw new RuntimeException('Servidor TLS local não iniciou.');
    }

    return [$process, $pipes, $port];
}

/** @param array{resource, array<int, resource>, int} $server */
function vault_test_stop_server(array $server): void
{
    [$process, $pipes] = $server;
    if (is_resource($pipes[0])) fclose($pipes[0]);
    if (is_resource($pipes[1])) fclose($pipes[1]);
    if (is_resource($pipes[2])) fclose($pipes[2]);
    $status = proc_get_status($process);
    if ($status['running']) {
        proc_terminate($process);
    }
    proc_close($process);
}

function vault_test_request(int $port, string $caPath, int $connectTimeoutMs = 500, int $requestTimeoutMs = 2000): array
{
    return (new CurlVaultHttpTransport())->request(
        'GET',
        'https://127.0.0.1:' . $port . '/test',
        ['Accept: application/json'],
        null,
        $connectTimeoutMs,
        $requestTimeoutMs,
        $caPath
    );
}

function vault_test_expect_failure(string $label, callable $callback, string|array $reason): void
{
    try {
        $callback();
    } catch (VaultOperationException $error) {
        $reasons = is_array($reason) ? $reason : [$reason];
        if (!in_array($error->reason(), $reasons, true)) {
            throw new RuntimeException('Falhou: ' . $label . ' retornou motivo seguro inesperado: ' . $error->reason());
        }
        vault_test_check($label . ' gera exceção sanitizada', true);
        vault_test_check($label . ' não retorna URL, corpo ou segredo', !str_contains($error->getMessage(), '127.0.0.1') && !str_contains($error->getMessage(), 'TEST-SECRET'));
        return;
    }
    throw new RuntimeException('Falhou: ' . $label . ' não foi recusado.');
}

function vault_test_server(array $args): void
{
    [$port, $mode, $certPath, $keyPath] = $args;
    $context = stream_context_create(['ssl' => [
        'local_cert' => $certPath,
        'local_pk' => $keyPath,
        'allow_self_signed' => true,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'disable_compression' => true,
    ]]);
    $scheme = $mode === 'connect-timeout' ? 'tcp' : 'tls';
    $server = @stream_socket_server($scheme . '://127.0.0.1:' . (int) $port, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
    if ($server === false) {
        fwrite(STDERR, "SERVER-ERROR\n");
        exit(2);
    }
    stream_set_timeout($server, 5);
    fwrite(STDOUT, "READY\n");
    fflush(STDOUT);
    $client = @stream_socket_accept($server, 5);
    if ($client === false) {
        fclose($server);
        exit(3);
    }

    if ($mode === 'connect-timeout') {
        usleep(1800000);
        fclose($client);
        fclose($server);
        exit(0);
    }

    while (($line = fgets($client, 8192)) !== false && trim($line) !== '') {
        // Drain request headers; their contents are intentionally not logged.
    }

    if ($mode === 'read-timeout') {
        fwrite($client, "HTTP/1.1 200 OK\r\nContent-Length: 20\r\nConnection: close\r\n\r\n");
        fflush($client);
        usleep(1800000);
    } elseif ($mode === 'large-headers') {
        fwrite($client, "HTTP/1.1 200 OK\r\n");
        for ($i = 0; $i < 500; $i++) {
            fwrite($client, 'X-Test-' . $i . ': ' . str_repeat('H', 80) . "\r\n");
        }
        fwrite($client, "Content-Length: 2\r\nConnection: close\r\n\r\nOK");
    } elseif ($mode === 'large-body') {
        $body = str_repeat('B', 131073);
        fwrite($client, "HTTP/1.1 200 OK\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
    } elseif ($mode === 'truncated') {
        fwrite($client, "HTTP/1.1 200 OK\r\nContent-Length: 100\r\nConnection: close\r\n\r\nshort");
    } elseif ($mode === 'redirect-http' || $mode === 'redirect-https') {
        $scheme = $mode === 'redirect-http' ? 'http' : 'https';
        fwrite($client, "HTTP/1.1 302 Found\r\nLocation: " . $scheme . '://127.0.0.1:' . (int) $port . "/redirect-target\r\nContent-Length: 3\r\nConnection: close\r\n\r\nRED");
    } else {
        fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: 11\r\nConnection: close\r\n\r\n{\"ok\":true}");
    }
    fflush($client);
    fclose($client);
    fclose($server);
}

$serverProcesses = [];
try {
    $configPath = $tempDir . DIRECTORY_SEPARATOR . 'openssl.cnf';
    $config = "[req]\ndistinguished_name = dn\nprompt = no\nreq_extensions = v3_req\n[dn]\n[v3_req]\nsubjectAltName = IP:127.0.0.1\n[v3_ca]\nbasicConstraints = critical,CA:TRUE\nkeyUsage = critical,keyCertSign,cRLSign\nsubjectKeyIdentifier = hash\n[v3_server]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature,keyEncipherment\nextendedKeyUsage = serverAuth\nsubjectAltName = IP:127.0.0.1\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid,issuer\n[v3_wrong_host]\nbasicConstraints = critical,CA:FALSE\nkeyUsage = critical,digitalSignature,keyEncipherment\nextendedKeyUsage = serverAuth\nsubjectAltName = DNS:wrong.invalid\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid,issuer\n";
    file_put_contents($configPath, $config, LOCK_EX);
    [$caKey, $caCertificate] = vault_test_ca('Kickoff test CA', $configPath);
    [$otherCaKey, $otherCaCertificate] = vault_test_ca('Untrusted test CA', $configPath);
    if (!openssl_x509_export($caCertificate, $caPem) || !openssl_x509_export($otherCaCertificate, $otherCaPem)) {
        throw new RuntimeException('Falha ao exportar CAs temporárias.');
    }
    $caPath = $tempDir . DIRECTORY_SEPARATOR . 'trusted-ca.pem';
    $otherCaPath = $tempDir . DIRECTORY_SEPARATOR . 'other-ca.pem';
    $invalidCaPath = $tempDir . DIRECTORY_SEPARATOR . 'invalid-ca.pem';
    file_put_contents($caPath, $caPem, LOCK_EX);
    file_put_contents($otherCaPath, $otherCaPem, LOCK_EX);
    file_put_contents($invalidCaPath, "not a PEM certificate\n", LOCK_EX);
    [$validCert, $validKey] = vault_test_server_certificate($tempDir, 'valid', $caCertificate, $caKey, $configPath, 'v3_server');
    [$wrongHostCert, $wrongHostKey] = vault_test_server_certificate($tempDir, 'wrong-host', $caCertificate, $caKey, $configPath, 'v3_wrong_host');
    [$untrustedCert, $untrustedKey] = vault_test_server_certificate($tempDir, 'untrusted', $otherCaCertificate, $otherCaKey, $configPath, 'v3_server');

    $server = vault_test_start_server('ok', $validCert, $validKey);
    $serverProcesses[] = $server;
    $response = vault_test_request($server[2], $caPath);
    vault_test_check('CA válida e certificado do servidor aceitos', $response['status'] === 200 && $response['body'] === '{"ok":true}');
    vault_test_stop_server($server);
    array_pop($serverProcesses);

    foreach ([
        ['CA inválida', 'ok', $validCert, $validKey, $invalidCaPath],
        ['hostname divergente', 'ok', $wrongHostCert, $wrongHostKey, $caPath],
        ['certificado não confiável', 'ok', $untrustedCert, $untrustedKey, $caPath],
    ] as [$label, $mode, $cert, $key, $trustedCa]) {
        $server = vault_test_start_server($mode, $cert, $key);
        $serverProcesses[] = $server;
        vault_test_expect_failure($label, fn () => vault_test_request($server[2], $trustedCa), 'tls_error');
        vault_test_stop_server($server);
        array_pop($serverProcesses);
    }

    foreach (['redirect-http', 'redirect-https'] as $redirectMode) {
        $server = vault_test_start_server($redirectMode, $validCert, $validKey);
        $serverProcesses[] = $server;
        $response = vault_test_request($server[2], $caPath);
        vault_test_check($redirectMode . ' não é seguido', $response['status'] === 302 && $response['body'] === 'RED');
        vault_test_stop_server($server);
        array_pop($serverProcesses);
    }

    $server = vault_test_start_server('connect-timeout', $validCert, $validKey);
    $serverProcesses[] = $server;
    vault_test_expect_failure('timeout de conexão/handshake', fn () => vault_test_request($server[2], $caPath, 250, 2000), 'timeout');
    vault_test_stop_server($server);
    array_pop($serverProcesses);

    $server = vault_test_start_server('read-timeout', $validCert, $validKey);
    $serverProcesses[] = $server;
    vault_test_expect_failure('timeout de leitura', fn () => vault_test_request($server[2], $caPath, 1000, 300), 'timeout');
    vault_test_stop_server($server);
    array_pop($serverProcesses);

    foreach ([
        ['resposta acima do limite', 'large-body', 'response_body_too_large'],
        ['cabeçalhos acima do limite', 'large-headers', 'response_headers_too_large'],
        ['resposta truncada', 'truncated', 'communication_error'],
    ] as [$label, $mode, $reason]) {
        $server = vault_test_start_server($mode, $validCert, $validKey);
        $serverProcesses[] = $server;
        vault_test_expect_failure($label, fn () => vault_test_request($server[2], $caPath), $reason);
        vault_test_stop_server($server);
        array_pop($serverProcesses);
    }

    $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
    if ($reservation === false) throw new RuntimeException('Falha ao preparar teste de conexão recusada.');
    $address = stream_socket_get_name($reservation, false);
    fclose($reservation);
    if (!is_string($address) || !preg_match('/:(\d+)$/', $address, $match)) throw new RuntimeException('Porta inválida no teste de conexão recusada.');
    vault_test_expect_failure('falha de conexão local', fn () => vault_test_request((int) $match[1], $caPath, 250, 500), ['communication_error', 'timeout']);

    echo 'OK: ' . $checks . " verificações do transporte cURL local.\n";
} finally {
    foreach (array_reverse($serverProcesses) as $server) {
        vault_test_stop_server($server);
    }
    foreach (glob($tempDir . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        if (is_file($path)) unlink($path);
    }
    rmdir($tempDir);
}
