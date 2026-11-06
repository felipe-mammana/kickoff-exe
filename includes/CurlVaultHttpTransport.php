<?php

declare(strict_types=1);

final class CurlVaultHttpTransport implements VaultHttpTransport
{
    private const MAX_RESPONSE_BYTES = 131072;
    private const MAX_HEADER_BYTES = 32768;

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $connectTimeoutMs,
        int $requestTimeoutMs,
        string $caFile
    ): array {
        if (!extension_loaded('curl')) {
            throw new VaultOperationException('transport_unavailable', 'Transporte HTTPS do Vault indisponível.');
        }

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new VaultOperationException('tls_configuration', 'Configuração TLS do Vault inválida.');
        }

        if (!is_file($caFile) || !is_readable($caFile)) {
            throw new VaultOperationException('tls_configuration', 'Certificado CA do Vault indisponível.');
        }

        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new VaultOperationException('invalid_request', 'Método HTTP do Vault não permitido.');
        }

        $responseHeaders = [];
        $responseHeaderBytes = 0;
        $responseHeadersTooLarge = false;
        $responseBody = '';
        $responseBodyTooLarge = false;
        $handle = curl_init();
        if ($handle === false) {
            throw new VaultOperationException('transport_unavailable', 'Transporte HTTPS do Vault indisponível.');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => $connectTimeoutMs,
            CURLOPT_TIMEOUT_MS => $requestTimeoutMs,
            CURLOPT_CAINFO => $caFile,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders, &$responseHeaderBytes, &$responseHeadersTooLarge): int {
                $length = strlen($line);
                if ($length > self::MAX_HEADER_BYTES - $responseHeaderBytes) {
                    $responseHeadersTooLarge = true;
                    return 0;
                }
                $responseHeaderBytes += $length;
                $separator = strpos($line, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($line, 0, $separator)));
                    $value = trim(substr($line, $separator + 1));
                    if ($name !== '') {
                        $responseHeaders[$name] = $value;
                    }
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody, &$responseBodyTooLarge): int {
                $length = strlen($chunk);
                if ($length > self::MAX_RESPONSE_BYTES - strlen($responseBody)) {
                    $responseBodyTooLarge = true;
                    return 0;
                }
                $responseBody .= $chunk;
                return $length;
            },
        ];

        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        try {
            if (!curl_setopt_array($handle, $options)) {
                throw new VaultOperationException('transport_configuration', 'Não foi possível configurar a comunicação com o Vault.');
            }

            $executed = curl_exec($handle);
            if ($executed === false) {
                if ($responseHeadersTooLarge) {
                    throw new VaultOperationException('response_headers_too_large', 'Cabeçalhos recebidos do Vault excedem o limite permitido.');
                }
                if ($responseBodyTooLarge) {
                    throw new VaultOperationException('response_body_too_large', 'Resposta recebida do Vault excede o limite permitido.');
                }
                throw $this->transportFailure(curl_errno($handle));
            }

            return [
                'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'headers' => $responseHeaders,
                'body' => $responseBody,
            ];
        } finally {
            curl_close($handle);
        }
    }

    private function transportFailure(int $curlError): VaultOperationException
    {
        if ($curlError === CURLE_OPERATION_TIMEDOUT) {
            return new VaultOperationException('timeout', 'Tempo limite de comunicação com o Vault excedido.', true);
        }

        $tlsErrors = [CURLE_SSL_CONNECT_ERROR];
        foreach ([
            'CURLE_PEER_FAILED_VERIFICATION',
            'CURLE_SSL_CACERT',
            'CURLE_SSL_CACERT_BADFILE',
            'CURLE_SSL_CERTPROBLEM',
            'CURLE_SSL_ISSUER_ERROR',
        ] as $constant) {
            if (defined($constant)) {
                $tlsErrors[] = constant($constant);
            }
        }

        if (in_array($curlError, array_unique($tlsErrors), true)) {
            return new VaultOperationException('tls_error', 'Falha na validação TLS do Vault.');
        }

        return new VaultOperationException('communication_error', 'Falha de comunicação com o Vault.', true);
    }
}
