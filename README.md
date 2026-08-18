# EXE Inventario TI

Sistema web simples em PHP e MySQL para cadastro e documentacao de dispositivos da empresa.

Funcionalidades principais:

- Administracao de empresas com padrao de etiqueta e status ativo/inativo.
- Cadastro dinamico de Notebook, CPU / Desktop, Roteador, Access Point, Modem e Impressora.
- Fotos gerais por dispositivo e foto de configuracao de rede para impressoras cabeadas.
- Filtros por empresa, tipo, etiqueta, colaborador, departamento, modelo, status e data de cadastro.
- Auditoria de logins, empresas, dispositivos e fotos.

## Estrutura

- `public/`: entrada publica da aplicacao, assets e uploads.
- `config/`: configuracao do ambiente e banco.
- `controllers/`: fluxo das telas e acoes.
- `models/`: consultas e persistencia.
- `views/`: templates PHP.
- `database/`: schema, migracoes e seed administrativo.
- `includes/`: bootstrap, helpers, sessao e seguranca basica.
- `Frontend-prototipo/`: referencias visuais do prototipo.

## Configuracao

O arquivo `config/config.php` possui defaults seguros para desenvolvimento e le valores de:

1. Variaveis de ambiente, quando existirem.
2. `config/local.php`, quando existir.
3. Valores padrao locais.

Crie sua configuracao local copiando o exemplo:

```powershell
Copy-Item config/local.example.php config/local.php
```

Depois edite `config/local.php` com banco, URL e usuario inicial. Esse arquivo e ignorado pelo Git.

## Como rodar localmente

Requisitos:

- PHP 7.4 ou superior com `pdo_mysql` e `fileinfo`
- MySQL 5.7 ou superior

1. Crie o banco importando `database/schema.sql` no MySQL.
2. Configure `config/local.php`, se necessario.
3. Inicie o MySQL no XAMPP.
4. Aplique a migracao. Ela pode ser rodada em banco novo ou existente:

```powershell
php database/apply_audit_migration.php
```

5. Crie o usuario inicial:

```powershell
php database/seed_admin.php
```

6. Inicie o servidor local:

```powershell
php -S localhost:8000 -t public
```

Se estiver usando XAMPP e o comando `php` nao for reconhecido no PowerShell, use o caminho completo:

```powershell
cd C:\Users\felip\OneDrive\Desktop\exe-kickoff
C:\xampp\php\php.exe database\apply_audit_migration.php
C:\xampp\php\php.exe database\seed_admin.php
C:\xampp\php\php.exe -S localhost:8000 -t public
```

7. Acesse `http://localhost:8000`.

Login inicial:

- E-mail: valor de `ADMIN_EMAIL`.
- Senha: valor de `ADMIN_PASSWORD`, ou a senha temporaria exibida pelo seed.

## Deploy no InfinityFree

No InfinityFree, todos os arquivos devem ficar dentro de `htdocs`. Para esse formato:

1. Gere ou use o pacote `infinityfree-htdocs.zip`.
2. Envie e extraia o zip dentro de `htdocs`.
3. Garanta que `htdocs/index.php` exista direto na raiz.
4. Configure `htdocs/config/local.php` ou edite as variaveis equivalentes em `htdocs/config/config.php`.
5. Importe `database/schema.sql` pelo phpMyAdmin.

As pastas internas possuem `.htaccess` para bloquear acesso direto via navegador.

## API

A API e versionada por URL e retorna JSON padronizado. A versao inicial fica em `/api/v1`.

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
    "message": "Endpoint da API nao encontrado.",
    "details": {}
  }
}
```

Endpoints iniciais:

- `GET /api/v1`: indice da versao e endpoints disponiveis.
- `GET /api/v1/health`: status publico da API.
- `GET /api/v1/me`: usuario logado.
- `GET /api/v1/device-types`: tipos de dispositivos.
- `GET /api/v1/companies?active_only=true`: empresas.
- `GET /api/v1/companies/{id}`: detalhe da empresa.
- `GET /api/v1/companies/{id}/machines`: dispositivos da empresa.
- `GET /api/v1/machines/{id}`: detalhe do dispositivo.
- `GET /api/v1/machines/{id}/photos`: fotos do dispositivo.

Por enquanto, os endpoints de dados usam a mesma sessao de login do sistema web. Campos sensiveis de senha de dispositivo nao sao expostos na API.

Listagens aceitam paginacao:

- `page`: pagina atual, padrao `1`.
- `per_page`: itens por pagina, padrao `25`, maximo `100`.

Exemplo:

```text
GET /api/v1/companies/1/machines?page=2&per_page=20&status=active
```

O retorno inclui `meta.pagination` com `page`, `per_page`, `total`, `last_page` e `has_more`.

Para adicionar novas rotas, edite `config/api_routes.php` e crie o metodo correspondente no controller da versao, como `ApiV1Controller`.

## Seguranca

- Senhas sao armazenadas com `password_hash`.
- Formularios usam token CSRF.
- Sessoes usam `httponly`, `SameSite=Lax` e modo estrito.
- Uploads permitem apenas JPG, PNG e WEBP, com limite de 5MB.
- `public/uploads` bloqueia execucao de PHP via `.htaccess`.
- Arquivos sensiveis locais devem ficar em `config/local.php`, que nao vai para o Git.
