<?php

declare(strict_types=1);

final class VaultWriteResult
{
    public function __construct(
        public readonly int $version,
        public readonly string $createdTime
    ) {
        if ($version < 1 || trim($createdTime) === '') {
            throw new InvalidArgumentException('Resultado de escrita do Vault inválido.');
        }
    }
}
