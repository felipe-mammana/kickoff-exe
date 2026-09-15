<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$server = stream_socket_server('tcp://127.0.0.1:' . (int) $argv[1], $errno, $error);
if (!$server) {
    throw new RuntimeException($error);
}
$mailFile = $argv[2];
$failureFile = $argv[3];
while ($socket = stream_socket_accept($server, -1)) {
    stream_set_timeout($socket, 5);
    @fwrite($socket, "220 local-test ESMTP\r\n");
    $recipient = '';
    $authStep = 0;
    while (($line = fgets($socket)) !== false) {
        $command = trim($line);
        if ($authStep > 0) {
            fwrite($socket, $authStep++ === 1 ? "334 UGFzc3dvcmQ6\r\n" : "235 OK\r\n");
            if ($authStep === 3) $authStep = 0;
        } elseif (str_starts_with($command, 'AUTH LOGIN')) {
            $authStep = 1;
            fwrite($socket, "334 VXNlcm5hbWU6\r\n");
        } elseif (str_starts_with($command, 'RCPT TO:')) {
            $recipient = $command;
            fwrite($socket, "250 OK\r\n");
        } elseif ($command === 'DATA') {
            fwrite($socket, "354 End with dot\r\n");
            $body = '';
            while (($line = fgets($socket)) !== false && rtrim($line, "\r\n") !== '.') $body .= $line;
            clearstatcache(true, $failureFile);
            if (is_file($failureFile)) {
                fwrite($socket, "550 Simulated test failure\r\n");
            } else {
                file_put_contents($mailFile, json_encode(['recipient' => $recipient, 'body' => $body], JSON_THROW_ON_ERROR) . "\n", FILE_APPEND | LOCK_EX);
                fwrite($socket, "250 Queued\r\n");
            }
        } elseif ($command === 'QUIT') {
            fwrite($socket, "221 Bye\r\n");
            break;
        } else {
            fwrite($socket, "250 OK\r\n");
        }
    }
    fclose($socket);
}
