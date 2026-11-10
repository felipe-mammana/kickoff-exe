<?php

declare(strict_types=1);

final class VaultSecret
{
    public function __construct(
        public readonly string $secret,
        public readonly int $companyId,
        public readonly int $credentialId,
        public readonly string $operationId,
        public readonly int $version,
        public readonly string $format = 'legacy:v1'
    ) {
        if ($companyId < 1 || $credentialId < 1 || $version < 1 || $secret === '' || strlen($secret) > 65536
            || !in_array($format, ['legacy:v1', 'enc:v2'], true)
        ) {
            throw new InvalidArgumentException('Segredo retornado pelo Vault inválido.');
        }
        VaultSecretPath::validateOperationId($operationId);
    }
}
