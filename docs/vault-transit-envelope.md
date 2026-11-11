# Criptografia de envelope do Cofre (HML)

## Estado verificado em 10/11/2026

- `vault-agent-cofre-hml.service`: ativo na SRV-APP.
- Vault: saudavel por TLS (`/v1/sys/health` retornou HTTP 200).
- Mount visivel ao token da aplicacao: somente `kickoff-cofre-hml/` (`kv`).
- O token recusa `auth/token/lookup-self` e `sys/capabilities-self` com HTTP 403.
- Nao foi possivel confirmar um mount Transit ou permissoes Transit. A funcionalidade permanece desativada por padrao.

Nenhuma politica, AppRole ou chave do Vault foi alterada durante a implementacao.

## Formato no KV v2

Novas escritas, quando `VAULT_COFRE_ENVELOPE_ENCRYPTION=1`, usam `schema_version=2` e armazenam:

```json
{
  "format": "enc:v2",
  "algorithm": "AES-256-GCM",
  "aad_version": 1,
  "ciphertext": "BASE64",
  "nonce": "BASE64_12_BYTES",
  "tag": "BASE64_16_BYTES",
  "wrapped_dek": "vault:v1:...",
  "company_id": 1,
  "credential_id": 1,
  "operation_id": "UUIDv4",
  "schema_version": 2
}
```

O AAD canonico vincula formato, empresa, credencial, UUID da operacao e versao KV. A DEK possui 256 bits, e exclusiva por escrita, existe em claro apenas na memoria do processo e e apagada quando `sodium_memzero` esta disponivel. O Vault Transit protege a DEK; o KV nunca recebe a DEK em claro.

Registros `schema_version=1` continuam legiveis. A leitura de `enc:v2` continua habilitada mesmo quando novas escritas criptografadas sao pausadas pela flag.

## Preparacao manual do Transit na HML

Estas operacoes exigem um operador Vault autorizado e devem ser revisadas antes da execucao. Nao usar token administrativo na aplicacao.

1. Confirmar se o mount `transit/` ja existe. Criar somente se estiver ausente.
2. Criar a chave `kickoff-cofre-hml` como `aes256-gcm96`, nao exportavel, sem backup em texto claro e sem exclusao permitida.
3. Anexar ao AppRole exclusivo da HML somente a politica de [exemplo](examples/vault-kickoff-cofre-hml-transit-policy.hcl).
4. Renovar o token do Vault Agent e validar apenas:
   - `update` em `transit/datakey/plaintext/kickoff-cofre-hml`;
   - `update` em `transit/decrypt/kickoff-cofre-hml`.
5. Nao conceder `transit/keys/*`, `transit/encrypt/*`, `sys/*`, `sudo`, `list` ou acesso a chaves de producao.

Configuracao privada da aplicacao:

```text
VAULT_COFRE_TRANSIT_MOUNT=transit
VAULT_COFRE_TRANSIT_KEY=kickoff-cofre-hml
VAULT_COFRE_ENVELOPE_ENCRYPTION=1
```

Ativar a flag somente depois de um teste de `datakey/plaintext` e `decrypt` com material ficticio. O endpoint `datakey/plaintext` retorna a DEK em Base64 e o respectivo ciphertext; `datakey/wrapped` retorna somente o ciphertext e nao e compativel com a criptografia AES-GCM local. A DEK em claro deve existir apenas na memoria do processo e nunca ser registrada ou persistida. Nao versionar esses valores em `config/local.php`.

## Migracao controlada

Nao existe migracao automatica ou em lote. Para cada credencial ficticia aprovada:

```bash
php database/migrate_vault_secret_encryption.php --credential-id=123
php database/migrate_vault_secret_encryption.php --credential-id=123 --apply
```

O primeiro comando e somente leitura. O segundo usa CAS, `lock_version` e o ledger existente, cria uma nova versao `enc:v2` e preserva todas as versoes anteriores. Reexecucao sobre uma versao ja `enc:v2` nao cria outra versao. Em timeout ou falha parcial, nao repetir a escrita: executar `database/reconcile_vault_secret_operations.php`.

## Rollback

- Antes da primeira escrita `enc:v2`: manter a flag em `0` restaura o comportamento anterior.
- Depois de existir `enc:v2`: manter o codigo de leitura Transit e a chave disponiveis. Desativar a flag pausa novas escritas criptografadas, mas nao elimina a necessidade de `decrypt` para registros existentes.
- Nao destruir a chave Transit nem versoes KV. Para voltar uma credencial ao formato anterior seria necessaria nova versao deliberada, com aprovacao e backup; nao ha rollback destrutivo automatico.

## Frontend

A revelacao nao reutiliza valores. O segredo e removido do DOM em ate 15 segundos, ao ocultar, fechar modal, trocar de aba, navegar ou receber erro. Requisicoes concorrentes usam `AbortController` e contador monotonicamente crescente para impedir resposta atrasada. A copia faz nova requisicao e nao persiste o segredo em atributo HTML ou storage. A area de transferencia nao e sobrescrita automaticamente, pois isso poderia apagar conteudo novo do usuario.
