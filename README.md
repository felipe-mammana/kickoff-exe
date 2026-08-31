# EXE Inventario TI

Sistema web simples em PHP e MySQL para cadastro e documentação de dispositivos da empresa.

Funcionalidades principais:

- Administração de empresas com padrão de etiqueta e status ativo/inativo.
- Cadastro dinâmico de Notebook, CPU / Desktop, Roteador, Access Point, Modem e Impressora.
- Fotos gerais por dispositivo e foto de configuração de rede para impressoras cabeadas.
- Filtros por empresa, tipo, etiqueta, colaborador, departamento, modelo, status e data de cadastro.
- Auditoria de logins, empresas, dispositivos e fotos.
- Gerenciamento administrativo de usuários, status de acesso e redefinicao de senha.
- Exportação CSV, JSON e DOCX; o DOCX por empresa inclui resumo, filtros, categorias, dados técnicos e fotos por tópico.

## Estrutura

- `public/`: entrada pública da aplicação, assets e uploads.
- `config/`: configuração do ambiente e banco.
- `controllers/`: fluxo das telas e ações.
- `models/`: consultas e persistência.
- `views/`: templates PHP.
- `database/`: schema, migrações e seed administrativo.
- `includes/`: bootstrap, helpers, sessão e segurança básica.
- `Frontend-prototipo/`: referencias visuais do prototipo.

## Configuração

O arquivo `config/config.php` possui defaults seguros para desenvolvimento e le valores de:

1. Variáveis de ambiente, quando existirem.
2. `config/local.php`, quando existir.
3. Valores padrão locais.

Crie sua configuração local copiando o exemplo:

```powershell
Copy-Item config/local.example.php config/local.php
```

Depois edite `config/local.php` com banco, URL e usuário inicial. Esse arquivo e ignorado pelo Git.

Gere uma `APP_KEY` própria para cada ambiente. Ela e usada para criptografar credenciais de dispositivos:

```powershell
php database/generate_app_key.php
```

Copie a linha gerada para `config/local.php`. Guarde essa chave: sem ela, credenciais já criptografadas não podem ser abertas.

## Como rodar localmente

Requisitos:

- PHP 7.4 ou superior com `pdo_mysql` e `fileinfo`
- MySQL 5.7 ou superior

1. Crie o banco importando `database/schema.sql` no MySQL. Para uma base sem nenhuma informação cadastrada, use `database/schema_empty.sql`.
2. Configure `config/local.php`, se necessário.
3. Inicie o MySQL no XAMPP.
4. Aplique a migração. Ela pode ser rodada em banco novo ou existente:

```powershell
php database/apply_audit_migration.php
php database/apply_credential_crypto_migration.php
php database/apply_login_rate_limit_migration.php
php database/apply_api_rate_limit_migration.php
php database/apply_user_management_migration.php
```

5. Crie o usuário inicial:

```powershell
php database/seed_admin.php
```

6. Inicie o servidor local:

```powershell
powershell.exe -ExecutionPolicy Bypass -File scripts\start_local_server.ps1
```

Se estiver usando XAMPP e o comando `php` não for reconhecido no PowerShell, use o caminho completo:

```powershell
cd C:\Users\felip\OneDrive\Desktop\exe-kickoff
C:\xampp\php\php.exe database\apply_audit_migration.php
C:\xampp\php\php.exe database\apply_credential_crypto_migration.php
C:\xampp\php\php.exe database\apply_login_rate_limit_migration.php
C:\xampp\php\php.exe database\apply_api_rate_limit_migration.php
C:\xampp\php\php.exe database\apply_user_management_migration.php
C:\xampp\php\php.exe database\seed_admin.php
powershell.exe -ExecutionPolicy Bypass -File scripts\start_local_server.ps1
```

7. Acesse `http://localhost:8000`.

Para testar o sistema inteiro com uma base limpa, siga o roteiro em `docs/test-system.md`.

Para rodar a validação automatizada básica, com o MySQL do XAMPP ligado:

```powershell
C:\xampp\php\php.exe tests\run.php
```

Esse comando usa um banco separado chamado `inventario_ti_test`, recria a estrutura a partir de `database/schema_empty.sql`, executa os testes e remove o banco no final. Para usar outro nome de banco de teste, defina `TEST_DB_NAME`.

Login inicial:

- E-mail: valor de `ADMIN_EMAIL`.
- Senha: valor de `ADMIN_PASSWORD`, ou a senha temporária exibida pelo seed.

## Deploy no InfinityFree

No InfinityFree, todos os arquivos devem ficar dentro de `htdocs`. Para esse formato:

1. Gere ou use o pacote `infinityfree-htdocs.zip`.
2. Envie e extraia o zip dentro de `htdocs`.
3. Garanta que `htdocs/index.php` exista direto na raiz.
4. Configure `htdocs/config/local.php` ou edite as variáveis equivalentes em `htdocs/config/config.php`.
5. Importe `database/schema.sql` pelo phpMyAdmin.

As pastas internas possuem `.htaccess` para bloquear acesso direto via navegador.

## API

A API e versionada por URL e retorna JSON padronizado. A versão inicial fica em `/api/v1`.

Formato de sucesso:

```json
{
  "ok": true,
  "data": {},
  "meta": {}
}
```

Formato de erro:

```json
{
  "ok": false,
  "error": {
    "code": "not_found",
    "message": "Endpoint da API não encontrado.",
    "details": {}
  }
}
```

Endpoints iniciais:

- `GET /api/v1`: índice da versão e endpoints disponíveis.
- `GET /api/v1/health`: status publico da API.
- `GET /api/v1/me`: usuário logado.
- `GET /api/v1/device-types`: tipos de dispositivos.
- `GET /api/v1/companies?active_only=true`: empresas.
- `POST /api/v1/companies`: cria empresa, restrito a administradores.
- `GET /api/v1/companies/{id}`: detalhe da empresa.
- `PUT /api/v1/companies/{id}`: atualiza empresa, restrito a administradores.
- `PATCH /api/v1/companies/{id}`: atualiza parcialmente empresa, restrito a administradores.
- `DELETE /api/v1/companies/{id}`: desativa empresa, restrito a administradores.
- `GET /api/v1/companies/{id}/machines`: dispositivos da empresa.
- `POST /api/v1/companies/{id}/machines`: cria dispositivo sem fotos.
- `GET /api/v1/machines/{id}`: detalhe do dispositivo.
- `PUT /api/v1/machines/{id}`: atualiza dispositivo, restrito a administradores.
- `PATCH /api/v1/machines/{id}`: atualiza parcialmente dispositivo, restrito a administradores.
- `DELETE /api/v1/machines/{id}`: desativa dispositivo, restrito a administradores.
- `GET /api/v1/machines/{id}/photos`: fotos do dispositivo.
- `POST /api/v1/machines/{id}/photos`: envia fotos do dispositivo.
- `DELETE /api/v1/machine-photos/{id}`: remove foto do dispositivo, restrito a administradores.

Campos omitidos nos updates são preservados e campos sensíveis de senha de dispositivo não são expostos na API.

Também e possível autenticar usando Bearer Token. Crie um token pela CLI:

```powershell
php database/creaté_api_token.php admin@empresa.com "Integração interna" 90
```

O terceiro argumento e opcional e define a validade em dias. Sem ele, o token não expira automaticamente.

Use o token nas chamadas:

```text
Authorization: Bearer exe_token_gerado
```

Tokens são armazenados no banco apenas como hash SHA-256. O valor completo aparece somente uma vez na criação.

As respostas da API incluem headers `X-RateLimit-Limit`, `X-RateLimit-Remaining` e `X-RateLimit-Reset`. Quando o limite e excedido, a API retorna `429` com `Retry-After`.

Para integrações, prefira sempre Bearer Token. Se uma rota mutável da API (`POST`, `PUT`, `PATCH`, `DELETE`) for chamada usando a sessão web do navegador, envie também o CSRF no header `X-CSRF-Token` ou no campo `csrf_token`.

Exemplos prontos para testar a API ficam em `docs/api-v1.http`. Ajuste `@baseUrl`, `@token` e os IDs no topo do arquivo antes de executar as chamadas.

Listagens aceitam páginação:

- `page`: página atual, padrão `1`.
- `per_page`: itens por página, padrão `25`, máximo `100`.

Exemplo:

```text
GET /api/v1/companies/1/machines?page=2&per_page=20&status=active
```

O retorno inclui `meta.pagination` com `page`, `per_page`, `total`, `last_page` e `has_more`.

Payload para criar empresa:

```json
{
  "name": "Nova Empresa",
  "tag_pattern": "NOTE-0001",
  "is_active": true
}
```

Erros de validação retornam `422` com `error.details.fields`.

Payload para atualizar empresa:

```json
{
  "name": "Empresa Atualizada",
  "tag_pattern": "ATU-0001",
  "is_active": true
}
```

No `PATCH`, envie apenas os campos que deseja alterar.

`DELETE /api/v1/companies/{id}` não apaga o registro fisicamente. Ele marca a empresa como inativa e preserva histórico/auditoria.

`DELETE /api/v1/machines/{id}` também não apaga o registro fisicamente. Ele marca o dispositivo como inativo e preserva histórico/auditoria. Essa ação e restrita a administradores.

Payload minimo para criar dispositivo:

```json
{
  "device_type": "outros",
  "tag": "API-001",
  "computer_model": "Equipamento generico",
  "notes": "Criado via API"
}
```

Cada `device_type` possui campos obrigatórios equivalentes ao formulario web. O endpoint de criação de dispositivo não recebe fotos no JSON; uploads entram em uma etapa separada da API.

Upload de fotos do dispositivo:

```text
POST /api/v1/machines/{id}/photos
Content-Type: multipart/form-data

photos[]=@foto-geral.jpg
network_photo[]=@configuracao-rede.png
photo_type=general
photos_topic[]=equipamento
network_photo_topic[]=ambiente
```

- `photos[]`: uma ou mais fotos gerais.
- `network_photo[]`: uma ou mais fotos de configuração de rede.
- `photo_type`: opcional para `photos[]`, aceita `general` ou `network_config`.
- `photos_topic[]`: opcional, um tópico por arquivo em `photos[]`, aceita `local`, `ambiente`, `equipamento` ou `outras`.
- `network_photo_topic[]`: opcional, um tópico por arquivo em `network_photo[]`.
- Formatos aceitos: JPG, PNG e WEBP, até 5MB por arquivo.

Remoção de foto:

```text
DELETE /api/v1/machine-photos/{id}
```

Remove o registro da foto, apaga o arquivo físico quando ele existe e preserva auditoria. Essa ação e restrita a administradores.

Para adicionar novas rotas, edite `config/api_routes.php` e crie o método correspondente no controller da versão, como `ApiV1Controller`.

## Segurança

- Senhas são armazenadas com `password_hash`.
- Usuários podem ser desativados; contas inativas não autenticam e tokens dessas contas não são aceitos.
- Formularios usam token CSRF.
- Rotas mutáveis da API com sessão web também exigem CSRF.
- Login possui limite de tentativas por e-mail e IP.
- API possui rate limit por IP em rotas públicas e por token/usuário em rotas autenticadas.
- Credenciais de dispositivos ficam criptografadas e não são descriptografadas em consultas comuns.
- Administradores podem revelar credenciais pontualmente pela tela do dispositivo; cada revelação gera auditoria.
- Respostas dinamicas enviam CSP, Permissions-Policy, COOP, headers anti-sniffing/frame e cache no-store.
- Sessoes usam `httponly`, `SameSite=Lax` e modo estrito.
- Uploads permitem apenas JPG, PNG e WEBP, com limite de 5MB.
- `public/uploads` bloqueia execução de PHP via `.htaccess`.
- Arquivos sensíveis locais devem ficar em `config/local.php`, que não vai para o Git.
