# EXE Kickoff: documentacao tecnica e operacional

Revisao: 10/09/2026. Base: codigo deste repositorio.
Nao constitui certificacao de seguranca. Exemplos nao incluem segredos reais.

## Indice

- [Arquitetura](#arquitetura)
- [Requisitos](#requisitos)
- [Instalacao local](#instalacao-local)
- [Configuracao](#configuracao)
- [Banco e migracoes](#banco-e-migracoes)
- [Seguranca](#seguranca)
- [Email e 2FA](#email-e-2fa)
- [API](#api)
- [Publicacao em servidor](#publicacao-em-servidor)
- [Backup e recuperacao](#backup-e-recuperacao)
- [Testes](#testes)
- [Diagnostico](#diagnostico)

## Arquitetura

Sistema de inventario de TI com empresas, equipamentos, fotos, anexos, usuarios,
cofre de credenciais, categorias/subcategorias, campos personalizados, auditoria,
exportacao CSV/JSON/DOCX e configuracoes de conta e operacao.

Backend PHP renderizado no servidor, organizado de forma semelhante a MVC, sem
framework principal. Frontend HTML/CSS/JavaScript nativos; nao e uma SPA. PDO
conecta a MySQL/MariaDB. Nao ha etapa obrigatoria de npm build nem manifesto
Composer na raiz atual. Bibliotecas locais implementam ZIP, TOTP e envio de email.

```text
Navegador -> public/index.php -> includes/bootstrap.php
         -> controlador -> autorizacao/CSRF/validacao
         -> modelo/PDO -> banco
         -> view PHP + assets -> navegador
```

Telas usam `/?route=...`, como `settings.twoFactor`. Rotas `/api/v1/...` passam
pelo ApiRouter antes do switch das telas.

| Pasta | Funcao |
| --- | --- |
| public | Unica raiz publica: index.php, assets e rewrite |
| config | Configuracoes privadas, defaults, PDO e rotas API |
| controllers | Casos de uso, autorizacao e validacao |
| models | Persistencia e regras de dados |
| includes | Bootstrap, helpers, criptografia, TOTP, email e exportadores |
| views | Templates PHP |
| database | Schemas, migracoes e ferramentas administrativas |
| storage | Sessoes, fotos, anexos e arquivos operacionais |
| tests | Testes unitarios, HTTP e UI opcionais |
| docs | Guias |

Fontes centrais: public/index.php, config/api_routes.php, includes/helpers.php,
models/User.php, controllers/SettingsController.php e includes/MicrosoftMail.php.

## Requisitos

Ambiente observado: Windows/XAMPP e PHP CLI 8.2.12. Isso descreve desenvolvimento,
nao recomenda congelar essa revisao em producao. Atualize para revisao de seguranca
suportada e homologue. O codigo usa funcoes PHP 8: PHP 7.4 nao e compativel.
Use PHP 8.2 ou superior como alvo de testes; nao ha matriz completa homologada.

Extensoes: PDO, pdo_mysql, fileinfo, OpenSSL, cURL, session, JSON, mbstring;
manter XML/DOM e zlib disponiveis para fluxos de documentos. SimpleZipWriter faz
parte do projeto; nao presumir dependencia obrigatoria de ext-zip para todo ZIP.

Banco MySQL/MariaDB com InnoDB, transacoes e utf8mb4. Conferir a versao real com
`SELECT VERSION();` e testar o schema no destino. Nao foi certificada uma versao
minima de banco nesta revisao.

Producao requer Apache com PHP ou Nginx com PHP-FPM, HTTPS, disco persistente e
permissao de escrita em storage. cURL precisa de CA confiavel. Sincronize relogio
do servidor para TOTP. Node/Playwright sao para testes de UI, nao para rodar o site.
Python e usado somente em ferramentas auxiliares.

## Instalacao local

Execute no PowerShell:

```powershell
Set-Location 'C:\Users\felip\OneDrive\Desktop\exe-kickoff'
& 'C:\xampp\php\php.exe' -v
& 'C:\xampp\php\php.exe' -m
if (!(Test-Path 'config/local.php')) {
    Copy-Item 'config/local.example.php' 'config/local.php'
}
```

Inicie MySQL no XAMPP. Crie banco vazio utf8mb4 e importe
`database/schema_empty.sql` pelo cliente SQL/phpMyAdmin, selecionando esse banco.
Nao importe schema sobre banco de producao existente.

Configure local.php (placeholders devem ser substituidos):

```php
<?php
return [
    'APP_ENV' => 'local',
    'APP_DEBUG' => true,
    'APP_URL' => 'http://localhost:8080',
    'APP_KEY' => 'CHAVE_GERADA',
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => 'inventario_ti',
    'DB_USER' => 'USUARIO_DO_BANCO',
    'DB_PASS' => 'SEGREDO_LOCAL',
];
```

Gere APP_KEY somente para instalacao nova:

```powershell
& 'C:\xampp\php\php.exe' database/generate_app_key.php
```

Guarde a chave em local.php e num cofre externo seguro. Em migracao de dados,
preserve a chave original, ou nao conseguira abrir as credenciais antigas.

Primeiro administrador, apenas em instalacao inicial controlada:

```powershell
$env:ADMIN_EMAIL = 'administrador@empresa.com.br'
$env:ADMIN_NAME = 'Administrador'
& 'C:\xampp\php\php.exe' database/seed_admin.php
Remove-Item Env:ADMIN_EMAIL
Remove-Item Env:ADMIN_NAME
```

Sem ADMIN_PASSWORD o script gera e exibe uma senha temporaria. Guarde-a e troque
no primeiro acesso. Se o email ja existe, seed_admin sobrescreve a senha e promove
a conta: NAO executar a cada deploy. Nao usar o SQL legado de admin predefinido.

Servidor apenas para desenvolvimento, em porta livre:

```powershell
& 'C:\xampp\php\php.exe' -S localhost:8080 -t public
```

Abra http://localhost:8080. Ctrl+C encerra. Para uso publico nao utilizar php -S.

## Configuracao

Ordem: variavel de ambiente nao vazia > config/local.php > default de config.php.
Arquivo .env nao e carregado automaticamente. config/microsoft.local.php e
independente e nao herda automaticamente APP_URL ou as variaveis SMTP.

| Parametro | Default/funcao |
| --- | --- |
| APP_NAME | EXE |
| APP_ENV / APP_DEBUG | local; debug local. Producao: production e false |
| APP_URL | http://localhost:8000 |
| APP_KEY | Chave para credenciais criptografadas |
| DB_HOST/DB_NAME/DB_USER/DB_PASS | Banco; nao usar root sem senha em producao |
| DB_CHARSET | utf8mb4 |
| MAIL_FROM/MAIL_FROM_NAME | Remetente SMTP/mail() |
| SMTP_HOST/SMTP_PORT | Host; porta default 587 |
| SMTP_USERNAME/SMTP_PASSWORD | Credenciais SMTP, nao segredo Graph |
| SMTP_ENCRYPTION | tls por default; ssl quando aplicavel |
| COMPANY_ATTACHMENT_MAX_BYTES | 25 MiB |
| API_MAX_JSON_BYTES | 1 MiB |
| API_RATE_LIMIT_WINDOW_SECONDS | 60 |
| API_RATE_LIMIT_PUBLIC_MAX_REQUESTS | 60 |
| API_RATE_LIMIT_AUTH_MAX_REQUESTS | 120 |

Fotos: limite base 5 MiB e MIME JPEG/PNG/WebP. AppSetting pode restringir limites
e tipos. Alinhar upload_max_filesize, post_max_size e limites do proxy com a
aplicacao. Aumentar limite PHP nao remove as validacoes da aplicacao.

## Banco e migracoes

Tabelas: users, companies, machines, machine_photos, company_attachments,
vault_categories, vault_credentials, audit_logs, app_settings, api_tokens,
login_attempts, api_rate_limits e security_rate_limits.

Empresas possuem dispositivos/anexos. Categorias do cofre podem ser locais
(company_id da empresa) ou globais (nulo). Criacoes locais nao devem virar padroes
globais; padroes sao gerenciados nas configuracoes. Modelos de categorias/cofre
persistem definicoes e valores dos campos personalizados, consumidos nos formularios.

Nao existe ledger central de migracoes. Alguns modelos verificam/criam estruturas
sob demanda. Para atualizar base existente, revisar scripts e homologar em copia
isolada. Nao importar schema nem executar todos os scripts indiscriminadamente.

Scripts disponiveis em database:

```text
apply_audit_migration.php
apply_user_management_migration.php
apply_credential_crypto_migration.php
apply_login_rate_limit_migration.php
apply_api_rate_limit_migration.php
apply_account_security_migration.php
apply_security_settings_migration.php
apply_user_preferences_migration.php
apply_vault_migration.php
apply_company_attachments_migration.php
apply_photo_metadata_migration.php
apply_account_protection_migration.php
```

Exemplo de migracao previamente revisada e com backup:

```powershell
& 'C:\xampp\php\php.exe' database/apply_account_protection_migration.php
```

A migracao de criptografia depende de APP_KEY correta. Backups devem acompanhar
o estado de schema; rollback de codigo sozinho pode nao reverter uma migracao.

## Seguranca

| Operacao | Usuario/viewer | Editor | Admin |
| --- | --- | --- | --- |
| Visualizar inventario/exportar relatorios permitidos | Sim | Sim | Sim |
| Criar/editar dados de negocio | Nao | Sim | Sim |
| Excluir dados administrativos | Nao | Nao | Sim |
| Gerenciar usuarios e auditoria administrativa | Nao | Nao | Sim |
| Configurar Microsoft/conectar/testar/desconectar | Nao | Nao | Sim |
| Configurar propria conta e 2FA | Sim | Sim | Sim |

require_auth, require_editor e require_admin aplicam controles no backend. API
tambem verifica perfil. Novas rotas precisam dos guards; esconder botoes nao basta.

Protecoes presentes:

- password_hash/password_verify para senhas de login.
- AES-256-GCM em CredentialCrypto; chave derivada de APP_KEY.
- Sessoes em storage/sessions; HttpOnly/SameSite=Lax, Secure quando HTTPS reconhecido.
- Validacao da sessao ativa contra banco e expiracao configuravel.
- Fluxos de troca/reset de senha pelo modelo invalidam sessoes e tokens API.
- CSRF nos formularios e escritas API autenticadas por sessao.
- Login: 5 falhas/10 minutos; consultar LoginAttempt para agrupamento por email/IP.
- 2FA: limite persistente padrao de 5 verificacoes/10 minutos.
- Email de codigo: 1 envio/minuto e 5/10 minutos por usuario.
- CSP, nosniff, politica de framing e demais headers em helpers.php.
- Excecao CSP form-action restrita as paginas de conexao Microsoft, apenas para
  login.microsoftonline.com.
- Consultas preparadas, escape de saida e validacao de arquivos nos fluxos existentes.
- Eventos de reveal examinados registram [protegido], nao valor real do segredo.

Limites que NAO devem ser ocultados na documentacao:

- Nao existe ACL geral de usuario por empresa. Filtro company_id nao equivale a
  isolamento multi-tenant. Acesso de leitura entre empresas pode ser permitido.
- machines.revealCredential exige sessao e CSRF, mas nao exige nova senha atualmente.
- Confirmacao/reautenticacao do cofre dependem de configuracao; nao sao universais.
- Quem obtem banco e APP_KEY pode descriptografar segredos. Backup e sensivel.
- is_https_request aceita X-Forwarded-Proto: o proxy deve sobrescrever esse header
  e a origem nao deve confiar em valores arbitrarios enviados diretamente por clientes.
- Com tunel/proxy, IP observado pode ser compartilhado, afetando rate limits.
- Lock de refresh Microsoft e local a instancia, nao coordenacao distribuida.
- Integracao OAuth utiliza cURL/PKCE implementados localmente, nao SDK externo.
- Testes pontuais nao certificam ausencia de vulnerabilidades.

## Email e 2FA

TwoFactorAuth implementa TOTP local: segredo aleatorio, HMAC-SHA1, periodo de 30
segundos e janela padrao de um periodo para cada lado. Aplicativos autenticadores
sao clientes compativeis; nao existe chamada a API Google para validar TOTP.

EmailCode gera codigos com random_int. Desafio de login pendente dura 5 minutos;
email de login acompanha o tempo restante. Outros fluxos possuem prazos proprios.
Nao confundir codigo para ativar email com codigo para entrar no sistema.

### Microsoft Graph delegado

1. Registrar aplicativo single-tenant no Entra.
2. Microsoft Graph > Delegadas: Mail.Send e User.Read.
3. Plataforma Web: cadastrar callback exato, sem concessao implicita.
4. Criar segredo do cliente; guardar o VALOR, nunca publicar.
5. Preencher config/microsoft.local.php:

```php
<?php
return [
    'client_id' => 'ID_CLIENTE',
    'tenant_id' => 'ID_LOCATARIO',
    'client_secret' => 'VALOR_PRIVADO',
    'redirect_uri' => 'http://localhost:8080/?route=settings.microsoft.callback',
    'sender' => 'remetente@empresa.com.br',
];
```

HTTP aceito somente para host exatamente localhost. Publico exige HTTPS. Ao mudar
o tunel, atualizar URI no arquivo e no Entra. Login administrativo deve estar no
mesmo hostname do callback para preservar a sessao.

Admin acessa Configuracoes > Email Microsoft > Conectar Microsoft. Conta diferente
do remetente configurado e rejeitada. offline_access e solicitado para renovacao.

```text
POST settings.microsoft.connect: admin + CSRF
 -> login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize
 -> settings.microsoft.callback: state de uso unico, sessao, 10 minutos, PKCE
 -> POST login.microsoftonline.com/{tenant}/oauth2/v2.0/token
 -> GET graph.microsoft.com/v1.0/me: verificar remetente
 -> tokens criptografados em app_settings
 -> POST graph.microsoft.com/v1.0/me/sendMail
```

Refresh token renova access token proximo do vencimento. Politicas, revogacao e
expiracao do segredo podem exigir reconexao. Nao depende do Outlook aberto.
Nao conceder Mail.Send de aplicativo para toda a organizacao para este fluxo delegado.

HTML de codigos: includes/CodeEmailTemplate.php; logo PNG incorporada por CID,
codigo destacado e aviso de nao compartilhar. Teste da conexao e texto simples.
Aceite HTTP 202 do Graph nao garante entrega: conferir spam/rastreamento Exchange.

Conexao Microsoft existente tem prioridade. Falha Graph nao cai silenciosamente
em SMTP. Desconectar apaga tokens locais; revogar consentimento no Entra e separado.
Depois de desconectar, transporte anterior volta a ser usado.

### SMTP e mail()

Sem Microsoft conectado: SMTP_HOST usa SMTP AUTH LOGIN. Caso nao configurado,
usa mail() do PHP. Nao existe SMTP OAuth nessa implementacao. Client secret Graph
nao e senha SMTP. Fallback SMTP atual envia texto com aviso de seguranca; HTML
esta integrado ao Graph. cURL/SMTP precisam de rede e CA validas: nao desativar TLS.

## API

Contrato v1 em config/api_routes.php. Nao confundir v1 com versao do produto.

| Metodo | Endpoint | Permissao |
| --- | --- | --- |
| GET | /api/v1, /api/v1/health | Publico |
| GET | /api/v1/me, /api/v1/device-types | Autenticado |
| GET/POST | /api/v1/companies | Leitura; criacao editor/admin |
| GET/PUT/PATCH/DELETE | /api/v1/companies/{id} | Ler; editar editor/admin; desativar admin |
| GET/POST | /api/v1/companies/{id}/machines | Ler; criar editor/admin |
| GET/PUT/PATCH/DELETE | /api/v1/machines/{id} | Ler; editar editor/admin; desativar admin |
| GET/POST | /api/v1/machines/{id}/photos | Ler; adicionar editor/admin |
| DELETE | /api/v1/machine-photos/{id} | Admin |

DELETE de empresa/dispositivo desativa conforme controlador, nao presumir exclusao
fisica. Para campos e filtros consulte API.md, ApiV1Controller e docs/api-v1.http.
O codigo prevalece em divergencias com exemplos antigos.

Bearer token ou sessao web; sessao precisa CSRF nas escritas. Token e persistido
por hash. Criacao opcional via CLI com validade explicita:

```powershell
& 'C:\xampp\php\php.exe' database/create_api_token.php 'usuario@empresa.com.br' 'Integracao' 30
$token = Read-Host 'Token de teste'
Invoke-RestMethod -Uri 'http://localhost:8080/api/v1/me' -Headers @{Authorization="Bearer $token"}
Remove-Variable token
```

Comando imprime token uma vez. Read-Host simples nao mascara: terminal privado,
sem compartilhamento/gravação. Nao usar dados reais em cargas de teste.

Envelope ok/data/meta ou ok/error. Defaults: 60 publico e 120 autenticado por
60 segundos; agrupamento definido por ApiRouter, nao assumir contador global.
Headers X-RateLimit-Limit, Remaining, Reset e Retry-After no bloqueio. Reset
neste codigo e tempo restante, nao Unix timestamp. Erros comuns: 429 limite,
419 CSRF, 403 perfil, 401 autenticacao conforme fluxo API.

## Publicacao em servidor

Transferir codigo atual: public, config (sem segredos locais), controllers,
models, includes, views. database apenas em local administrativo privado para
migracoes. Transferir fotos/anexos de storage em migracao e criar sessoes vazias.

Nao publicar .git, .agents, tests, docs, node_modules, output, security-reports,
Frontend-prototipo, backups SQL/ZIP, logs, sessoes ou pacotes antigos de deploy.
Nao assumir que deploy_infinityfree/server_upload_clean acompanham o codigo atual.

```text
/srv/exe-kickoff/
  config/       privado
  controllers/ privado
  models/       privado
  includes/     privado
  views/        privado
  storage/      privado, gravavel pelo servico
  public/       DocumentRoot
```

Criar config/local.php e config/microsoft.local.php no destino por canal seguro.
APP_ENV=production, APP_DEBUG=false e APP_URL correto. Preservar APP_KEY ao migrar
banco existente. Para ambiente novo, chave propria. Nao usar root sem senha.

Exemplo Apache para homologacao local (nao configura HTTPS):

```apache
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot "C:/Users/felip/OneDrive/Desktop/exe-kickoff/public"
    <Directory "C:/Users/felip/OneDrive/Desktop/exe-kickoff/public">
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Producao deve usar pasta dedicada fora de sincronizacao pessoal OneDrive e HTTPS
com certificado valido. Apache requer mod_rewrite para public/.htaccess. Nginx
nao interpreta .htaccess: configurar try_files/index.php e PHP-FPM explicitamente.
Nunca servir a raiz inteira confiando apenas em arquivos .htaccess.

```powershell
& 'C:\xampp\apache\bin\httpd.exe' -t
```

Esse comando valida configuracao Apache antes de reiniciar pelo painel/servico.
Codigo nao deve ser gravavel pelo usuario web; storage deve ser. DDL sob demanda
exige revisar privilegios do banco e homologar antes de retirar permissoes.

Roteador: destino e IP LAN reservado do servidor, nao publico. Expor somente
portas necessarias ao HTTPS/validacao de certificado. Nao ativar DMZ nem abrir
MySQL, phpMyAdmin, RDP ou php -S. Computador desligado/suspenso deixa site indisponivel.
IP dinamico e tunel temporario nao garantem endereco estavel.

Checklist:

- Backup restauravel, APP_KEY preservada, configuracoes privadas.
- HTTPS, callback correto, debug desligado, permissoes de storage.
- Banco migrado em homologacao antes da producao.
- Testar admin/editor/viewer e operacoes de escrita proibidas.
- Testar 2FA, email, anexos, exportacao e cofre com dados ficticios.
- Confirmar que configuracoes/scripts/backups nao respondem via HTTP.
- Validar limites de upload, relogio, rede, espaco e logs.
- Preparar rollback de codigo e banco coerentes.

## Backup e recuperacao

Manutencao exporta SQL e ZIP com SQL/fotos/anexos. ZIP nao inclui necessariamente
APP_KEY/configuracao/codigo; preservar esses itens separadamente. Importacao do
painel e SQL, nao restauracao automatica de um ZIP inteiro.

IMPORTANTE: "banco limpo" nao e vazio nem anonimizado. DatabaseMaintenance omite
algumas tabelas operacionais e limpa campos de sessao; ainda pode conter dados
pessoais, hashes, credenciais e tokens Microsoft criptografados em app_settings.
Nao compartilhar esse dump publicamente.

Recuperacao: interromper escritas, preservar estado atual, restaurar SQL em banco
isolado, restaurar anexos/fotos e APP_KEY correspondente, ajustar permissoes/URL,
descartar sessoes antigas, testar contagens/perfis/credenciais e so entao publicar.
Limpeza de orfaos e destrutiva: revisar resumo, ter backup e evitar uploads concorrentes.
Retencao configurada nao prova limpeza agendada; verificar jobs operacionais reais.

## Testes

Testes pequenos sem banco:

```powershell
& 'C:\xampp\php\php.exe' tests/microsoft_mail.php
& 'C:\xampp\php\php.exe' tests/microsoft_csp.php
& 'C:\xampp\php\php.exe' tests/microsoft_admin_access.php
& 'C:\xampp\php\php.exe' tests/code_email.php
node --check public/assets/js/app.js
```

microsoft_admin_access usa stub do guard: nao substitui testes HTTP de perfis.
Testes pequenos OAuth nao realizam consentimento ou entrega real na Microsoft.

Suite integrada DESTRUTIVA para o banco de teste selecionado:

```powershell
$env:TEST_DB_NAME = 'inventario_documentacao_test'
& 'C:\xampp\php\php.exe' tests/run.php
Remove-Item Env:TEST_DB_NAME
```

Cria/apaga banco; nome deve conter test e ser diferente do banco configurado.
Use instancia/usuario isolados, nunca credenciais de producao. Consulte
[test-system.md](test-system.md) para SMTP simulado e Playwright/Edge opcional.

Versionar mudancas de schema/contrato; testar mobile/desktop e guards de novas
rotas. Monitorar entrega de email, segredo vencendo, erros PHP, banco e disco.
Nao logar codigos, senhas, tokens ou respostas completas de OAuth.

## Diagnostico

| Sintoma | Verificar |
| --- | --- |
| SQLSTATE 2002 / login 500 | MySQL iniciado, host/porta/credenciais |
| 419 | Sessao/CSRF; atualizar e entrar no mesmo hostname |
| Usuario recebe 403 em users.index | Esperado |
| Configuracao Microsoft pendente | IDs/segredo/URI no arquivo privado |
| Botao bloqueado por CSP | Recarregar e conferir headers antigos do proxy |
| Retorno cai no login | Sessao do hostname do callback ausente/expirada |
| Redirect URI mismatch | Scheme/host/porta/caminho/query no Entra e arquivo |
| Email nao chega | Reconexao, segredo, CA, rede, spam/rastreamento Exchange |
| Credencial invalida apos migrar | APP_KEY incorreta ou dados incompletos |
| API 404 | Rewrite/DocumentRoot e rota |
| Upload rejeitado | MIME/tamanho e limites PHP/proxy/configuracao |

## Referencias

- [OAuth Microsoft](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-auth-code-flow)
- [Graph sendMail](https://learn.microsoft.com/en-us/graph/api/user-sendmail)
- [Permissoes Graph](https://learn.microsoft.com/en-us/graph/permissions-reference)
- [Servidor de desenvolvimento PHP](https://www.php.net/manual/en/features.commandline.webserver.php)
- [CSP form-action](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy/form-action)

As fontes externas explicam protocolos; o codigo instalado define o comportamento
do EXE. Um botao visivel ou resposta 200 isolada nao comprova escalada: registrar
identidade, sessao, endpoint e operacao efetivamente testada.
