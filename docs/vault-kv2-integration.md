# Integracao funcional do cofre com Vault KV v2

O valor secreto de uma credencial existe somente no mount
`kickoff-cofre-hml`. O MySQL guarda metadados, estado, versao e o UUID da
operacao que produziu a versao confirmada.

## Configuracao

A aplicacao aceita somente configuracao nao secreta por ambiente:

```text
VAULT_COFRE_ADDRESS=https://10.10.10.30:8200
VAULT_COFRE_CA_FILE=/usr/local/share/ca-certificates/kickoff-vault-ca.crt
VAULT_COFRE_TOKEN_FILE=/run/kickoff-cofre-hml/token
VAULT_COFRE_CONNECT_TIMEOUT_MS=1000
VAULT_COFRE_REQUEST_TIMEOUT_MS=3000
```

O mount e fixado internamente como `kickoff-cofre-hml`. O token nao e carregado
no bootstrap: `VaultTokenProvider` le o sink do Agent somente quando uma operacao
do cofre chama o Vault. Assim, token ausente nao impede o funcionamento dos
outros modulos.

## Fluxos

`VaultCredentialSecretService` coordena MySQL e Vault:

- cadastro: cria metadado `pending` e ledger, grava com CAS 0, le a versao 1 e
  confirma somente se IDs, UUID e segredo em memoria coincidirem;
- atualizacao com segredo: registra o ledger, usa CAS da versao atual, confirma
  por leitura e atualiza a referencia com `lock_version`;
- atualizacao sem segredo: altera somente metadados e incrementa o lock;
- revelacao e exportacao: leem exatamente `vault_version` e comparam
  `vault_operation_id`;
- desativacao: altera somente `is_active`; nenhuma versao e apagada do Vault.

Notas e campos personalizados continuam criptografados no MySQL. Nenhum segredo,
token, corpo HTTP ou resposta bruta e persistido no ledger ou na auditoria.

## Falhas parciais e reconciliacao

Timeouts e erros retryable deixam a operacao `pending`. Uma resposta de escrita
seguida de falha na finalizacao deixa `pending` ou `vault_confirmed`. A aplicacao
nao repete o POST.

O reconciliador calcula `expected_vault_version + 1`, executa somente uma leitura
dessa versao e compara o `operation_id` retornado. Se coincidir, avanca a
referencia MySQL e conclui o ledger. Se outra operacao estiver na versao, marca
conflito; se a versao nao existir, marca falha definitiva. Nenhuma senha e
necessaria para reconciliar.

Execucao manual futura, exclusivamente em homologacao:

```bash
APP_ENV=homologacao php database/reconcile_vault_secret_operations.php 50
```

Saida 2 indica pendencia ou falha de auditoria e exige revisao. O comando nao
deve ser agendado ate a validacao da Entrega C.

## Auditoria e respostas

Cadastro, atualizacao, revelacao, exportacao, desativacao e reconciliacao usam
eventos sem valores protegidos. O controller somente devolve sucesso depois que
a operacao principal esta consistente e a auditoria obrigatoria foi registrada.
Se a persistencia da auditoria falhar depois de uma escrita confirmada, a
interface orienta a nao repetir a operacao.

Revelacao usa `Cache-Control: no-store` e nunca aceita caminho Vault fornecido
pelo navegador. Exportacao recupera todas as versoes antes de construir o
`EncryptedBackup`; qualquer falha cancela o arquivo inteiro.

## Backup e restauracao

`DatabaseMaintenance` exporta apenas a referencia Vault presente no MySQL. Um
backup SQL ou ZIP nao contem os segredos. Restaurar o banco exige que o mesmo
mount e as versoes referenciadas continuem disponiveis; a restauracao isolada do
MySQL nao constitui backup completo do cofre.

## Implantacao coordenada

1. validar os testes em MySQL 8.0.46 descartavel;
2. fazer backup restauravel de `kickoff_hml` e validar o backup do Vault por
   procedimento administrativo separado;
3. colocar o modulo em manutencao e confirmar `vault_credentials` vazia;
4. aplicar a Entrega A manualmente;
5. implantar a Entrega B no mesmo periodo de manutencao;
6. validar token do Agent, CA, policy, cadastro, edicao, revelacao, exportacao e
   desativacao;
7. simular timeout e executar o reconciliador antes de liberar usuarios.

O schema novo nao deve ser aplicado mantendo o controller antigo, e o controller
novo nao deve ser liberado contra o schema antigo.
