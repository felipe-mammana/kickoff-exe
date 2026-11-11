<?php

declare(strict_types=1);

final class FakeVaultKv2Transport implements VaultHttpTransport
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $versions = [];
    private bool $timeoutAfterNextWrite = false;
    private bool $conflictNextWrite = false;
    private int $dataKeyCounter = 0;
    /** @var list<string> */
    public array $requestPaths = [];

    /** @return array<string, mixed> */
    public function versionData(int $companyId, int $credentialId, int $version): array
    {
        return $this->versions[$companyId . ':' . $credentialId][$version] ?? [];
    }

    public function replaceVersionData(int $companyId, int $credentialId, int $version, array $data): void
    {
        $this->versions[$companyId . ':' . $credentialId][$version] = $data;
    }

    public function timeoutAfterNextWrite(): void
    {
        $this->timeoutAfterNextWrite = true;
    }

    public function conflictNextWrite(): void
    {
        $this->conflictNextWrite = true;
    }

    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $connectTimeoutMs,
        int $requestTimeoutMs,
        string $caFile
    ): array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $this->requestPaths[] = $method . ' ' . $path;
        if ($method === 'POST' && str_contains($path, '/datakey/wrapped/')) {
            $this->dataKeyCounter++;
            $key = hash('sha256', 'fake-transit-key-' . $this->dataKeyCounter, true);
            // Resposta real de /datakey/wrapped: nao inclui a DEK em plaintext.
            return $this->response(200, ['data' => [
                'ciphertext' => 'vault:v1:' . base64_encode($key),
            ]]);
        }
        if ($method === 'POST' && str_contains($path, '/datakey/plaintext/')) {
            $this->dataKeyCounter++;
            $key = hash('sha256', 'fake-transit-key-' . $this->dataKeyCounter, true);
            return $this->response(200, ['data' => [
                'plaintext' => base64_encode($key),
                'ciphertext' => 'vault:v1:' . base64_encode($key),
            ]]);
        }
        if ($method === 'POST' && str_contains($path, '/decrypt/')) {
            $payload = json_decode((string) $body, true, 8, JSON_THROW_ON_ERROR);
            $wrapped = (string) ($payload['ciphertext'] ?? '');
            $key = str_starts_with($wrapped, 'vault:v1:') ? base64_decode(substr($wrapped, 9), true) : false;
            if (!is_string($key) || strlen($key) !== 32) {
                return $this->response(400, ['errors' => ['invalid ciphertext']]);
            }
            return $this->response(200, ['data' => ['plaintext' => base64_encode($key)]]);
        }
        if (preg_match('#/data/empresas/(\d+)/credenciais/(\d+)$#', $path, $matches) !== 1) {
            return $this->response(404, ['errors' => ['not found']]);
        }
        $key = $matches[1] . ':' . $matches[2];

        if ($method === 'POST') {
            if ($this->conflictNextWrite) {
                $this->conflictNextWrite = false;
                return $this->response(400, ['errors' => ['check-and-set parameter did not match the current version']]);
            }
            $payload = json_decode((string) $body, true, 32, JSON_THROW_ON_ERROR);
            $cas = (int) ($payload['options']['cas'] ?? -1);
            $current = count($this->versions[$key] ?? []);
            if ($cas !== $current) {
                return $this->response(400, ['errors' => ['check-and-set parameter did not match the current version']]);
            }
            $version = $current + 1;
            $this->versions[$key][$version] = (array) ($payload['data'] ?? []);
            if ($this->timeoutAfterNextWrite) {
                $this->timeoutAfterNextWrite = false;
                throw new VaultOperationException('timeout', 'Tempo limite de comunicacao com o Vault excedido.', true);
            }
            return $this->response(200, [
                'data' => [
                    'version' => $version,
                    'created_time' => '2026-01-01T00:00:00Z',
                    'destroyed' => false,
                    'deletion_time' => '',
                ],
            ]);
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $version = (int) ($query['version'] ?? 0);
        if (!isset($this->versions[$key][$version])) {
            return $this->response(404, ['errors' => ['not found']]);
        }
        return $this->response(200, [
            'data' => [
                'data' => $this->versions[$key][$version],
                'metadata' => [
                    'version' => $version,
                    'destroyed' => false,
                    'deletion_time' => '',
                ],
            ],
        ]);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private function response(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'headers' => [],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ];
    }
}
