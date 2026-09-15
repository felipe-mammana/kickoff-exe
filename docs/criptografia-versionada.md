# Criptografia versionada: primeira etapa

Formato novo: enc:v2:purpose:key-id:base64(iv + tag + ciphertext).
AES-256-GCM; IV aleatorio 12 bytes; tag 16 bytes. Purpose e key-id sao autenticados
como AAD. Chaves devem ser aleatorias, 32 bytes em base64, diferentes por finalidade.

Finalidades: credentials (cofre/equipamentos), totp e microsoft.
config/crypto.example.php e o modelo; crypto.local.php e privado e ignorado pelo Git.
Nao ative chaves sem backup seguro e plano de recuperacao. Sem active configurado,
novas escritas continuam em v1; dados antigos continuam usando APP_KEY.

Rotacao: adicionar ID novo, manter os IDs antigos para leitura e selecionar o novo
active para novas escritas. Recriptografia de registros antigos requer migracao
controlada, com validacao de leitura antes de gravar e backup. Nunca apagar APP_KEY
enquanto houver dados v1, nem remover uma chave usada em backup ainda retido.

Estado desta etapa: biblioteca versionada, integracao de finalidades e ferramenta
CLI de migracao implementadas; chaves de producao NAO provisionadas e banco NAO
migrado pelo assistente. Campos personalizados, backups criptografados e KMS pendentes.
Isso nao e conhecimento zero nem criptografia completa de todos os dados.

Teste: php tests/versioned_crypto.php (sem banco e sem chaves reais).

## Procedimento de ativacao e rotacao

Primeiro homologue em copia isolada e preserve backup do banco e APP_KEY.
Interrompa processos web/workers/envios durante ativacao e migracao.

```powershell
F
& 'C:\xampp\php\php.exe' database/rotate_crypto.php
```

O primeiro comando cria crypto.local.php com tres chaves aleatorias independentes,
sem sobrescrever arquivo existente e sem imprimir valores. Ele ATIVA novas escritas
v2; copie as chaves para custodia segura antes de reiniciar o aplicativo. No Windows,
chmod nao substitui ACL NTFS: conceda leitura apenas ao servico e administradores.
Nao apagar o arquivo apos novas escritas. O segundo comando apenas simula, mas
precisa conseguir ler todas as chaves existentes.

Com manutencao confirmada, backup recuperavel e simulacao bem-sucedida:

```powershell
& 'C:\xampp\php\php.exe' database/rotate_crypto.php --apply --maintenance-confirmed
```

A flag e uma declaracao do operador, nao interrompe o servidor automaticamente.
Migracao usa transacao e bloqueio de linhas, valida descriptografia antes de gravar
e nao imprime valores. Requer tabelas transacionais InnoDB e schema atualizado.
Ler todas as linhas em memoria limita o uso em bases grandes: homologar volume e
janela de manutencao. Texto puro inesperado ou dado corrompido cancela a operacao.
O processo nao modifica backups antigos: preservar as chaves desses backups.

Escopo: senhas de maquinas/cofre, segredo TOTP e tokens Microsoft. Metadados,
campos personalizados e arquivos nao sao abrangidos por essa migracao.
Teste adicional: php tests/crypto_rotation.php, com configuracao temporaria isolada.

## Execucao local em 13/09/2026

Com autorizacao explicita para descartar credenciais ficticias ilegíveis, a
migracao local foi concluida: quatro segredos do cofre, tres senhas legiveis de
maquinas e uma conexao Microsoft migrados. Um campo machine_password ilegivel
foi esvaziado; nenhum dispositivo, usuario ou empresa foi excluido. Nao havia
segredos TOTP nem admin_password preenchidos nesse processamento.

Backup previo criptografado salvo em storage/crypto-backups; a leitura do backup
foi conferida e todos os valores restantes foram validados apos a migracao.
Isso nao constitui ensaio completo de restauracao SQL nem teste real de envio.

A opcao --discard-invalid-test-credentials em apply_crypto_upgrade.php e exclusiva
para descarte explicitamente autorizado de dados ficticios: nao usar em producao.
Ela nao descarta tokens Microsoft nem segredos TOTP. Chaves ativas sao verificadas
antes de processar registros. Preservar APP_KEY e crypto.local.php.
