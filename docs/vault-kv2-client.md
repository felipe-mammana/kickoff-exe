# Cliente PHP Vault KV v2

## Escopo

O cliente implementa somente a comunicação segura com um mount KV v2. Ele ainda não é chamado por controller, modelo ou migration e não contém credenciais ou valores específicos de produção.

As classes ficam em `includes/` porque o autoloader atual procura classes por nome nessa pasta. `VaultWriteResult` e `VaultSecret` são objetos de retorno explícitos, evitando arrays sem contrato.

## Configuração futura

Todos os valores são fornecidos ao construtor. A configuração de homologação prevista é:

| Parâmetro | Referência de homologação |
|---|---|
| Endereço | `https://10.10.10.30:8200` |
| Mount | `kickoff-cofre-hml` |
| CA | `/usr/local/share/ca-certificates/kickoff-vault-ca.crt` |
| Token | `/run/kickoff-cofre-hml/token` |

O token não é carregado no bootstrap. `VaultTokenProvider` lê o arquivo a cada operação, com leitura limitada a 8 KiB mais um byte de detecção, permitindo que o Vault Agent faça sua substituição sem reiniciar o PHP-FPM e impedindo que a ausência do Agent afete módulos não relacionados. A substituição concorrente do arquivo é rejeitada com erro sanitizado; a operação deve ser reconciliada pela camada chamadora, sem retry cego de escrita.

Exemplo de composição futura:

```php
$client = new VaultKv2Client(
    new CurlVaultHttpTransport(),
    new VaultTokenProvider($tokenFile),
    new VaultSecretPath($mount),
    $address,
    $caFile,
    $connectTimeoutMs,
    $requestTimeoutMs
);
```

Nenhum desses valores deve vir diretamente de parâmetros HTTP.

## Contratos

```php
create(int $companyId, int $credentialId, string $secret, string $operationId): VaultWriteResult
read(int $companyId, int $credentialId, int $version): VaultSecret
update(int $companyId, int $credentialId, string $secret, int $expectedVersion, string $operationId): VaultWriteResult
```

O caminho é sempre construído como:

```text
/v1/{mount}/data/empresas/{company_id}/credenciais/{credential_id}
```

Criações usam `cas=0`; atualizações usam a versão esperada. Leituras exigem uma versão positiva e nunca consultam implicitamente a versão mais recente.

`operation_id` deve ser UUIDv4 canônico em minúsculas, com variante RFC válida. O payload armazenado contém `secret`, `company_id`, `credential_id`, `operation_id` e `schema_version`. Leituras rejeitam divergência dos IDs, versão destruída ou apagada e `operation_id` malformado.

O endpoint de escrita do KV v2 retorna metadados de versão, mas não repete o payload gravado. A comparação do `operation_id` e do segredo após uma escrita será feita pelo serviço de integração futuro por meio de uma leitura explícita da versão retornada.

## TLS e transporte

- Somente URLs `https` são aceitas.
- A CA precisa ser um arquivo legível.
- `CURLOPT_SSL_VERIFYPEER` permanece ativo.
- `CURLOPT_SSL_VERIFYHOST` é fixado em `2`.
- Redirects HTTP são desabilitados.
- O protocolo permitido é HTTPS quando suportado pelo cURL instalado.
- O corpo da resposta é limitado a 128 KiB e o conjunto de cabeçalhos a 32 KiB durante o recebimento; o transporte aborta a transferência ao exceder qualquer limite.
- Timeouts de conexão aceitos: 100 a 10.000 ms.
- Timeout total: igual ou superior ao de conexão e no máximo 30.000 ms.

Não há retry automático. Em particular, uma escrita que falha de modo ambíguo deve ser reconciliada pelo serviço futuro antes de qualquer nova tentativa.

## Erros seguros

`VaultOperationException` fornece um motivo estável e uma mensagem sanitizada. HTTP 400 só é classificado como `cas_conflict` quando a resposta contém a mensagem conhecida de CAS do KV v2; os demais HTTP 400 são `validation_error`. Nenhum dos casos inclui o corpo bruto na exceção:

| Motivo | Situação |
|---|---|
| `token_unavailable` | arquivo ausente, ilegível ou falha de leitura |
| `token_invalid` | token vazio, excessivo ou com caracteres inválidos |
| `tls_configuration` | URL ou CA inadequada |
| `tls_error` | validação TLS falhou |
| `timeout` | timeout do transporte |
| `communication_error` | falha de rede |
| `forbidden` | HTTP 403 |
| `not_found` | HTTP 404 |
| `rate_limited` | HTTP 429 |
| `server_error` | HTTP 5xx |
| `cas_conflict` | HTTP 400 com erro CAS KV v2 reconhecido |
| `validation_error` | HTTP 400 sem assinatura de conflito CAS |
| `invalid_response` | JSON ou estrutura inválida |
| `response_body_too_large` | corpo acima de 128 KiB |
| `response_headers_too_large` | cabeçalhos acima de 32 KiB |

Corpos de erro do Vault, tokens, segredos e URLs completas não são incorporados às exceções.

## Testes

`tests/vault_kv2_client.php` usa um transporte falso e arquivos temporários com dados fictícios. `tests/vault_curl_transport.php` gera CA e certificados temporários e inicia um servidor TLS em loopback. Nenhum dos testes acessa Vault, banco ou rede externa.

Execução:

```text
php tests/vault_kv2_client.php
php tests/vault_curl_transport.php
```

## Integração posterior

Uma camada de serviço deverá:

1. obter empresa e credencial de registros já validados;
2. gerar o UUID da operação;
3. chamar `create` ou `update`;
4. ler explicitamente a versão retornada;
5. comparar segredo e `operation_id` em memória;
6. confirmar versão, shadow e auditoria no MySQL.

O cliente não implementa autorização de usuário, CSRF, sessão do cofre, auditoria, persistência ou fallback legado. Essas responsabilidades permanecem nas camadas da aplicação.
