<?php

declare(strict_types=1);

final class VaultKv2ClientFactory
{
    public static function create(?VaultHttpTransport $transport = null): VaultKv2Client
    {
        if (!in_array(APP_ENV, ['homologacao', 'testing'], true)) {
            throw new VaultCredentialServiceException(
                'environment_not_allowed',
                'Operacoes do cofre Vault estao habilitadas somente em homologacao.'
            );
        }

        return new VaultKv2Client(
            $transport ?? new CurlVaultHttpTransport(),
            new VaultTokenProvider(VAULT_COFRE_TOKEN_FILE),
            new VaultSecretPath(VAULT_COFRE_MOUNT),
            VAULT_COFRE_ADDRESS,
            VAULT_COFRE_CA_FILE,
            VAULT_COFRE_CONNECT_TIMEOUT_MS,
            VAULT_COFRE_REQUEST_TIMEOUT_MS
        );
    }
}
