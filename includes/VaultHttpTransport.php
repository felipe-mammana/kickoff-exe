<?php

declare(strict_types=1);

interface VaultHttpTransport
{
    /**
     * @param list<string> $headers
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function request(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $connectTimeoutMs,
        int $requestTimeoutMs,
        string $caFile
    ): array;
}
