# Criptografia: status e evidencias

Revisao: 13/09/2026. Resultado: requisito PARCIALMENTE atendido.
Este documento comprova implementacoes especificas, nao certifica o cofre inteiro.
Nao inclui chaves, senhas ou ciphertext de registros reais.

## Matriz do requisito enviado

| Item | Estado | Evidencia ou lacuna |
| --- | --- | --- |
| Criptografia por campo de senha | Implementada | Cofre/equipamentos usam CredentialCrypto |
| AES-256-GCM | Implementado | VersionedCrypto.php:14 |
| Nonce aleatorio por criptografia | Implementado | random_bytes(12), VersionedCrypto.php:12 |
| Autenticacao/integridade do ciphertext | Implementada | Tag de 16 bytes e verificacao no decrypt |
| Chaves de 256 bits por finalidade | Implementadas | prepare_crypto_keys.php:8-9, validadas em VersionedCrypto |
| Identificador de chave e rotacao | Implementados | Envelope v2, CredentialCrypto::rotate e CryptoRotation |
| Senhas de login sem texto puro | Implementado | User::create/updatePassword usam password_hash |
| Argon2id explicitamente configurado | Pendente | Codigo usa PASSWORD_DEFAULT |
| Senha-mestra e KDF dela | Nao implementados | Modelo atual confia em chaves do servidor |
| Todos os campos sensiveis protegidos | Pendente | Observacoes e campos personalizados nao sao automaticamente criptografados |
| Backups sempre criptografados | Parcial | Backup da migracao criptografado; exportacoes comuns SQL/ZIP nao |
| TLS 1.2/1.3 em producao | Nao verificado | Exige teste do servidor/proxy, nao apenas codigo PHP |
| Criptografia de disco/banco inteiro | Nao verificada | Configuracao de infraestrutura |

Nao e necessario usar AES e XChaCha20 simultaneamente. A ausencia de XChaCha20
nao e uma lacuna se AES-GCM estiver adequadamente implementado e operado.
Salt para hashing de senha e nonce para AES-GCM sao conceitos diferentes.

## Evidencia 1: algoritmo, nonce e integridade

Trecho de [VersionedCrypto.php](../includes/VersionedCrypto.php):

```php
$iv = random_bytes(12);
$aad = 'exe:enc:v2:' . $purpose . ':' . $id;
$cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $aad, 16);
if ($cipher === false) throw new RuntimeException('Encryption failed');
return 'enc:v2:' . $purpose . ':' . $id . ':' . base64_encode($iv . $tag . $cipher);
```

IV aleatorio de 96 bits; tag de 128 bits. Finalidade e ID da chave entram no AAD,
portanto alteracao desses dados invalida a autenticacao. Base64 so transporta
bytes: nao e a criptografia. O nonce aleatorio reduz colisao, mas nao representa
prova matematica de unicidade em volume ilimitado.

Na leitura:

```php
$plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key($parts[3], $keys),
    OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16),
    'exe:enc:v2:' . $purpose . ':' . $parts[3]);
if ($plain === false) throw new RuntimeException('Encrypted data authentication failed');
```

Limite: o AAD atual nao vincula o ciphertext a ID de registro/empresa/campo.
Nao protege contra troca de envelopes validos entre registros da mesma finalidade
por um invasor com escrita no banco. Vinculacao por registro e melhoria futura.

## Evidencia 2: chaves independentes

Trecho de [prepare_crypto_keys.php](../database/prepare_crypto_keys.php):

```php
foreach (['credentials', 'totp', 'microsoft'] as $purpose) {
    $rings[$purpose] = ['active' => 'key1', 'keys' => ['key1' => base64_encode(random_bytes(32))]];
}
```

O leitor exige exatamente 32 bytes. O arquivo privado crypto.local.php e ignorado
pelo Git. Isso nao equivale a KMS/HSM nem comprova ACL NTFS correta. As chaves
ainda residem no mesmo servidor e precisam de custodia e backup seguro.

## Evidencia 3: rotacao verificada

[CredentialCrypto::rotate](../includes/CredentialCrypto.php) le v1 ou v2 e valida
o valor recriptografado antes de retorna-lo:

```php
$new = VersionedCrypto::encrypt($plain, $purpose, $ring['active'], $ring['keys'] ?? []);
if (!hash_equals($plain, VersionedCrypto::decrypt($new, $purpose, $ring['keys'] ?? []))) {
    throw new RuntimeException('Falha na verificacao da migracao.');
}
```

[CryptoRotation](../models/CryptoRotation.php) usa transacao e bloqueio de linhas
na aplicacao. O comportamento normal e interromper em erro. O descarte opcional
de credenciais ficticias e uma excecao explicitamente autorizada, nao recuperacao.
Rotacao nao modifica backups anteriores; chaves antigas continuam necessarias.

## Evidencia 4: backup da migracao

Trecho de [apply_crypto_upgrade.php](../database/apply_crypto_upgrade.php):

```php
$cipher = openssl_encrypt($dump, 'aes-256-gcm', hash('sha256', APP_KEY, true), OPENSSL_RAW_DATA,
    $iv, $tag, 'exe:database-backup:v1', 16);
```

A rotina compara o hash do SQL original com o do SQL descriptografado do arquivo
gravado. Isso confirma integridade/leitura do backup, NAO restauracao integral
do banco. A chave usada e a APP_KEY existente, nao uma chave externa de backup.
DatabaseMaintenance::dumpSql/fullBackupZip continuam gerando SQL/ZIP sem essa
camada no painel: nao anunciar criptografia universal dos backups.

## Evidencia 5: senha de login

Trecho de [User.php](../models/User.php):

```php
'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
```

No PHP 8.2 observado, PASSWORD_DEFAULT usa bcrypt. Nao significa Argon2id.
A senha armazenada no cofre precisa ser recuperavel e usa AES; substituir esse
campo por hash tornaria impossivel revelar a senha original.

## Resultado operacional registrado

Na migracao local de 13/09/2026: quatro segredos do cofre, tres senhas de maquinas
e tokens de uma conexao Microsoft migrados. Um campo de senha ilegivel de teste
foi esvaziado com autorizacao. Nao havia segredos TOTP preenchidos para migrar.
Esse e o resultado daquela execucao; nao e inventario permanente do banco.

## Testes reexecutados nesta revisao

```powershell
& 'C:\xampp\php\php.exe' tests/versioned_crypto.php
& 'C:\xampp\php\php.exe' tests/crypto_rotation.php
```

Ambos passaram: roundtrip, nonce diferente entre duas chamadas, rejeicao de
adulteracao, finalidade incorreta e chave ausente, rotacao, leitura legada,
tres finalidades e registros invalidos. Sao testes locais com dados ficticios,
nao revisao criptografica independente nem prova de TLS/infraestrutura.

## Para encerrar este requisito

1. Classificar campos sensiveis e criptografar observacoes/customizados conforme politica,
   incluindo migracao, redacao de logs e carregamento autorizado na interface.
2. Integrar criptografia a todos os backups operacionais, separar custodia e testar restauracao.
3. Verificar TLS no endpoint real e no caminho ate o banco/proxy conforme arquitetura.
4. Implementar Argon2id para autenticacao quando suportado, com migracao por login.
5. Formalizar modelo server-side; senha-mestra/conhecimento zero sao projeto separado,
   nao afirmar que existem. Revisar gestao de chaves e vinculacao por registro.

Conclusao: a etapa de criptografia versionada dos segredos foi entregue; o grupo
completo de requisitos ainda tem pendencias. Nenhuma nova migracao foi executada
para produzir este documento.
