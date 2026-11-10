# Estrutura MySQL do cofre Vault KV v2

Esta estrutura e destinada a uma instalacao limpa. O MySQL armazena somente
metadados e a referencia versionada; o valor secreto existe exclusivamente no
HashiCorp Vault KV v2.

O caminho nao e persistido no banco. Ele deve ser derivado pela aplicacao:

```text
empresas/{company_id}/credenciais/{credential_id}
```

Em homologacao, o mount e `kickoff-cofre-hml`. Os scripts desta entrega nao se
conectam ao Vault e nao modificam a integracao existente do MySQL com o Vault
Agent.

## Modelo de `vault_credentials`

Os campos relacionados ao segredo sao:

- `operational_state`: `pending`, `ready` ou `error`;
- `vault_version`: versao KV v2 lida explicitamente;
- `vault_operation_id`: UUIDv4 canonico e minusculo da ultima escrita confirmada;
- `lock_version`: contador para updates otimistas.

`is_active` continua reservado a ativacao ou desativacao logica. Uma credencial
`ready` pode estar ativa ou inativa. A integracao funcional devera consultar
simultaneamente `operational_state = 'ready'` e `is_active = 1` quando a operacao
exigir uma credencial utilizavel.

As invariantes SQL sao:

| Estado | `vault_version` | `vault_operation_id` |
|---|---:|---|
| `pending` | `NULL` | `NULL` |
| `ready` | positiva | UUIDv4 |
| `error` | `NULL` | `NULL` |

Falha em uma atualizacao nao deve apagar uma referencia `ready` anterior. Nesse
caso a credencial permanece pronta na versao confirmada e a falha fica registrada
no ledger. O estado `error` representa um cadastro que ainda nao possui segredo
confirmado.

Nao existem `secret_value`, `secret_backend`, `vault_path`,
`shadow_vault_version` ou campos de migracao legada no schema definitivo.
`notes` e `custom_fields` permanecem no MySQL e deverao continuar criptografados
pela aplicacao.

## Controle otimista e CAS

A Entrega B devera manter estas regras:

- criacao no Vault com CAS `0`;
- atualizacao com CAS igual a `vault_version`;
- leitura pela versao explicitamente armazenada;
- atualizacao MySQL condicionada a `lock_version`.

Exemplo do update otimista:

```sql
UPDATE vault_credentials
SET vault_version = :new_version,
    vault_operation_id = :operation_id,
    lock_version = lock_version + 1
WHERE id = :id
  AND vault_version = :expected_vault_version
  AND lock_version = :expected_lock_version;
```

Zero linhas alteradas representa conflito. A escrita Vault nao deve ser repetida
sem reconciliar primeiro o UUID e a versao esperada.

## Ledger `vault_secret_operations`

O ledger aceita somente operacoes `create` e `update`, nos estados:

```text
pending -> vault_confirmed -> completed
pending -> failed
```

- `create` exige `expected_vault_version = 0` e `expected_lock_version = 0`;
- `update` exige uma versao esperada positiva;
- uma versao resultante, quando presente, deve ser a esperada mais um;
- `pending` e `failed` nao admitem versao resultante;
- `vault_confirmed` e `completed` exigem versao resultante;
- `completed` exige `completed_at`;
- `failed` exige `error_code` sanitizado;
- UUIDs sao validados no mesmo formato aceito por `VaultKv2Client`;
- a mesma versao resultante nao pode ser registrada duas vezes para uma credencial.

Nao ha colunas para senha, token, caminho, payload HTTP ou resposta bruta. O
`error_code` admite somente letras maiusculas, numeros e `_`, com ate 64
caracteres.

## Instalacao futura em homologacao

O script e somente CLI e recusa qualquer alvo diferente de:

```text
APP_ENV=homologacao
DB_NAME efetivo=kickoff_hml
```

O nome efetivo do banco e obtido pela configuracao ja fornecida pelo Vault Agent.
Antes da execucao futura:

1. confirmar que a branch e o artefato correspondem a homologacao;
2. confirmar o mount `kickoff-cofre-hml` na configuracao da aplicacao;
3. obter e verificar um backup restauravel de `kickoff_hml`;
4. colocar o modulo em manutencao;
5. confirmar que `vault_credentials` esta vazia;
6. revisar o diff SQL e a saida do preflight;
7. executar manualmente:

```bash
APP_ENV=homologacao php database/apply_vault_kv2_migration.php
```

O instalador nunca executa `DROP DATABASE`, nao altera usuarios, empresas,
categorias ou permissoes e nao reseta `AUTO_INCREMENT`. Se encontrar registros
enquanto ainda houver colunas legadas a remover, ele aborta antes da alteracao
destrutiva. Um ledger legado somente e recriado quando esta vazio.

Cada `ALTER TABLE` e DDL atomico no MySQL 8, mas a sequencia inteira nao constitui
uma unica transacao. Por isso o script consulta `INFORMATION_SCHEMA` antes de cada
passo e pode ser executado novamente depois de uma interrupcao. Mesmo em tabela
vazia, DDL pode obter metadata locks; a janela de manutencao continua obrigatoria.

## Rollback estrutural

O rollback tambem e manual e somente CLI:

```bash
APP_ENV=homologacao php database/rollback_vault_kv2_migration.php
```

Ele e recusado se existir qualquer credencial ou operacao no ledger. Quando as
tabelas estao vazias, remove o ledger e os quatro campos Vault e restaura
`secret_value TEXT NOT NULL` somente para recompor o formato estrutural anterior.
Nenhum ID e reiniciado. O rollback nao remove caminhos nem versoes do Vault.

Depois da Entrega B ou da primeira escrita real no Vault, a estrategia preferida
e corrigir para frente. Qualquer limpeza de caminhos deve ser um procedimento
administrativo separado, limitado ao mount de homologacao e explicitamente
aprovado.

## Teste descartavel

O teste nao le `config/local.php` e exige opt-in literal antes de abrir uma
conexao. `DB_HOST` deve ser loopback e a porta deve ser 3306. O nome do banco e
gerado internamente no formato `kickoff_vault_kv2_test_<16 hex>`.

Exemplo em PowerShell, somente com um MySQL local descartavel:

```powershell
$env:VAULT_KV2_TEST_DISPOSABLE = 'YES_I_UNDERSTAND'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_USER = 'root'
$env:DB_PASS = '<senha-local-de-teste>'
php tests/vault_kv2_database.php
```

Antes de criar o banco, o teste consulta `INFORMATION_SCHEMA.SCHEMATA` e recusa
um nome preexistente. No encerramento, executa `DROP DATABASE` sem `IF EXISTS`
somente quando a mesma execucao marcou que criou aquele nome. Nenhum endereco da
SRV-DB e aceito.

O teste cobre schema, ausencia dos campos legados, defaults, estados, UUIDv4,
constraints, chaves estrangeiras, CAS representado no ledger, controle otimista,
idempotencia, bloqueio com dados e rollback vazio.
