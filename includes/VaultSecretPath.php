<?php

declare(strict_types=1);

final class VaultSecretPath
{
    public function __construct(private readonly string $mount)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/D', $mount) !== 1) {
            throw new InvalidArgumentException('Mount KV v2 inválido.');
        }
    }

    public function dataPath(int $companyId, int $credentialId): string
    {
        self::validateId($companyId, 'empresa');
        self::validateId($credentialId, 'credencial');

        return '/v1/' . $this->mount . '/data/empresas/' . $companyId . '/credenciais/' . $credentialId;
    }

    public static function validateOperationId(string $operationId): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $operationId) !== 1) {
            throw new InvalidArgumentException('Identificador de operação deve ser UUIDv4 canônico em minúsculas.');
        }

        return $operationId;
    }

    private static function validateId(int $id, string $label): void
    {
        if ($id < 1) {
            throw new InvalidArgumentException('Identificador de ' . $label . ' inválido.');
        }
    }
}
