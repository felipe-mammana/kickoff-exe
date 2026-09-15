# Protecao operacional do cofre

Implementado em 13/09/2026. Permissoes e MFA nao foram alterados.

## Bloqueio

VaultSession exige senha atual na primeira entrada e apos 1200 segundos sem uma
requisicao autorizada ao cofre. A verificacao ocorre no servidor, inclui as rotas
de configuracao protegidas por require_vault_access e usa o limitador de senha.
Respostas recebem Cache-Control no-store. Paginas do cofre recarregam apos vinte
minutos para retirar dados da tela; digitar ou mover o mouse nao prolonga esse prazo.
O bloqueio nao encerra o login no restante do sistema. O navegador ja autorizado
pode ter copiado dados antes do bloqueio; isto nao pode ser desfeito pelo servidor.

## Campos

Observacoes e todo o JSON de campos personalizados sao criptografados ao gravar
e descriptografados ao ler, com a chave versionada de credenciais. Nomes, usuarios,
URLs e categorias continuam pesquisaveis e nao foram criptografados nesta etapa.
O script database/encrypt_vault_metadata.php suporta simulacao e --apply.
Aplicacao local: quatro registros processados, com backup criptografado previo.
Nao ha alteracao automatica de historico antigo de auditoria ou backups anteriores.

## Backups

Downloads do painel: .exe-sql e .exe-zip, envelopes autenticados criptografados.
O painel importa .exe-sql e SQL legado. O ZIP deve ser descriptografado antes de
restaurar anexos; nao foi criado um importador automatico de ZIP.

```powershell
C:\xampp\php\php.exe database/decrypt_backup.php entrada.exe-zip saida.zip zip
C:\xampp\php\php.exe database/decrypt_backup.php entrada.exe-sql saida.sql sql
```

O destino deve ser novo. Proteja as copias em claro e remova-as apos uso.
Preserve config/crypto.local.php separado dos backups, incluindo chaves antigas.
APP_KEY ainda e necessaria para dados legados. Perder as chaves pode inviabilizar
a recuperacao. Esta etapa reutiliza a finalidade credentials; KMS, chave exclusiva
de backup e armazenamento externo imutavel continuam pendentes.

## Auditoria

Revelacoes sao registradas independentemente da preferencia antiga; checkbox
desabilitado no painel. Criacao/edicao mascaram observacoes e campos personalizados
nos novos logs. Desbloqueios sao registrados. O saneamento de payloads antigos
foi aplicado em 18 registros, com backup criptografado previo. Ele mascara chaves
sensiveis conhecidas; nao prova ausencia de segredos em texto livre ou descricoes.
Revelacao de senha e consulta dos arquivos protegidos falham se a auditoria falhar.
Outras operacoes ainda podem concluir mesmo com falha de log.
Copia efetiva no clipboard nao foi instrumentada.

### Arquivos protegidos

Novos eventos tambem sao gravados em storage/protected-audit/AAAA-MM-DD.txt.enc,
com data UTC. Cada linha e criptografada; nao e TXT em claro. Os arquivos devem
ficar fora do document root (public). Configuracoes > Arquivos de auditoria exige
administrador, CSRF e senha atual a cada consulta, com limite de tentativas.
A senha confirma acesso no aplicativo; nao e uma senha de pasta do Windows.
O Explorer continua sujeito as ACLs da conta Windows. Quem possui as chaves do
servidor pode descriptografar os arquivos; um administrador do host pode apaga-los.
Nao se trata de armazenamento imutavel nem de um servico externo.
Preserve as chaves e configure backup/retencao da pasta. Os arquivos nao entram
automaticamente no ZIP comum. Testes usam subpasta testing separada.

### Recuperacao dos anexos

```powershell
C:\xampp\php\php.exe -d extension=zip database/stage_backup.php entrada.exe-zip C:\recuperacao-exe-nova
```

Exige extensao PHP zip (nao habilitada por padrao no PHP local inspecionado).
O comando habilita a extensao apenas nessa execucao, sem alterar php.ini.
Recupera SQL, manifesto e anexos em pasta nova e confere SHA-256 de cada arquivo.
Recusa caminhos fora da estrutura prevista e destinos existentes. Nao sobrescreve
o sistema em uso. A pasta recuperada contem dados em claro e deve ser protegida.

## Testes

Executar `php -d extension=zip tests/run.php`, incluindo bloqueio inicial, recusa de
senha, desbloqueio, headers, criptografia de metadados, adulteracao de backup,
roundtrip ZIP, restauracao SQL no banco isolado, extracao de anexo com hash,
recusa de path traversal e acesso aos arquivos de auditoria por perfil e senha.
O ensaio de desastre em outro servidor e verificacao visual mobile permanecem pendentes.
