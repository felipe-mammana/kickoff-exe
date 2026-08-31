# Documentação do Projeto EXE Inventario TI

## 1. Resumo executivo

O EXE Inventario TI e um sistema web interno para cadastro, organização e auditoria de ativos de tecnologia. A aplicação centraliza empresas, dispositivos, fotos, histórico de alterações, usuários administrativos e uma API versionada para integrações.

O objetivo principal e substituir controles manuais ou planilhas por uma base única, rastreável e com critérios de segurança básicos para operação interna.

## 2. Objetivos do sistema

- Centralizar o inventario de dispositivos de TI por empresa.
- Registrar notebooks, desktops, roteadores, access points, modems, impressoras e outros ativos.
- Guardar fotos e evidencias dos equipamentos.
- Manter histórico auditavel de logins, cadastros, alterações e exclusoes lógicas.
- Disponibilizar API REST versionada para consultas e integrações futuras.
- Melhorar a segurança no armazenamento de senhas e tokens.

## 3. Tecnologias utilizadas

- Backend: PHP.
- Banco de dados: MySQL/MariaDB.
- Frontend: HTML, CSS e JavaScript sem dependencia pesada de framework.
- Servidor local: XAMPP ou servidor embutido do PHP.
- Deploy simples: hospedagem PHP/MySQL, como InfinityFree, ou túnel temporario via Cloudflare Tunnel.

## 4. Principais funcionalidades

### Empresas

- Cadastro de empresas.
- Edicao de dados.
- Status ativo/inativo.
- Padrão de etiqueta por empresa.
- Exportação de dados.

### Dispositivos

- Cadastro por tipo de equipamento.
- Campos dinâmicos conforme tipo:
  - notebook;
  - CPU / desktop;
  - roteador;
  - access point;
  - modem;
  - impressora;
  - outros.
- Checklist operacional, incluindo TFlux, antivirus e solicitante.
- Senhas técnicas protegidas por criptografia.
- Upload de fotos gerais e fotos de configuração de rede.
- Visualização detalhada do dispositivo.
- Desativação logica, preservando histórico.

### Auditoria

- Registro de eventos relevantes do sistema.
- Histórico de login com sucesso e falha.
- Registro de criação, edicao e desativação de empresas e dispositivos.
- Registro de upload e remoção de fotos.
- Filtros por usuário, empresa, dispositivo, ação e periodo.
- Exportação de logs em CSV e JSON.
- Modal para visualizar alterações em JSON sem expandir a tabela.

### Usuários

- Login autenticado.
- Perfil administrador.
- Controle basico de permissao para rotas administrativas e API.

## 5. Arquitetura do projeto

O projeto foi organizado em camadas simples para facilitar manutencao:

```text
public/       Entrada pública da aplicação, assets e uploads
config/       Configurações, rotas da API e variáveis locais
controllers/  Regras de fluxo das telas e endpoints
models/       Acesso ao banco e persistência
views/        Templatés PHP da interface
includes/     Bootstrap, helpers, sessão e segurança
database/     Schema, migrações e scripts auxiliares
docs/         Documentação e arquivos de teste
storage/      Sessoes e arquivos internos
```

O arquivo publico principal e `public/index.php`. As requisicoes web e de API passam por ele e são direcionadas aos controllers correspondentes.

## 6. Segurança implementada

### Senhas de usuários

As senhas de login dos usuários não são salvas em texto puro. Elas são armazenadas usando `password_hash`, recurso nativo do PHP proprio para senhas.

Na autenticação, o sistema utiliza `password_verify`, evitando comparação manual e reduzindo risco de falhas comuns.

### Credenciais técnicas dos dispositivos

As senhas cadastradas nos dispositivos, como `machine_password` e `admin_password`, são protegidas com criptografia reversivel usando AES-256-GCM.

Esse modelo permite:

- armazenar as credenciais criptografadas no banco;
- descriptografar apenas quando o sistema precisa exibir para usuário autorizado;
- evitar que o banco mostre senhas diretamente em caso de consulta simples;
- válidar integridade do dado criptografado por meio do modo GCM.

A chave da criptografia fica em `APP_KEY`, definida no arquivo `config/local.php` ou em variavel de ambiente. Essa chave não deve ir para o Git.

Observação importante: se a `APP_KEY` for perdida ou trocada depois de haver dados criptografados, as credenciais antigas não poderao ser abertas corretamente.

### Tokens de API

A API aceita autenticação por Bearer Token.

O token completo aparece apenas uma vez no momento da criação. No banco, o sistema armazena somente o hash SHA-256 do token.

Isso reduz o impacto caso alguem consulte diretamente a tabela de tokens, pois o valor real usado para autenticar chamadas não fica salvo em texto puro.

### CSRF em formularios

Os formularios da interface web usam token CSRF para reduzir risco de envio indevido de ações a partir de páginas externas.

Esse controle está presente em operações como:

- login;
- logout;
- cadastro e edicao;
- exclusao/desativação;
- remoção de fotos.

### Sessoes

As sessões usam configurações de segurança:

- `HttpOnly`, reduzindo acesso por JavaScript;
- `SameSite=Lax`, reduzindo envio indevido de cookies em contexto externo;
- modo estrito de sessão;
- regeneração de ID apos login.

### Uploads

Os uploads possuem validações:

- formatos aceitos: JPG, PNG e WEBP;
- limite de 5 MB por arquivo;
- validação por MIME type;
- nomes finais gerados com bytes aleatorios;
- bloqueio de execução de PHP dentro de `public/uploads` via `.htaccess`.

### Auditoria e rastreabilidade

O sistema registra eventos em `audit_logs`, incluindo:

- usuário;
- e-mail;
- tipo de ação;
- tabela afetada;
- registro afetado;
- empresa e dispositivo relacionados;
- IP de origem;
- data/hora;
- dados antigos e novos quando aplicavel.

Campos sensíveis, como senhas de dispositivos, são mascarados nos logs como `[protegido]`.

## 7. API versionada

A API foi estruturada com versionamento por URL. A versão atual e:

```text
/api/v1
```

### Padrão de resposta de sucesso

```json
{
  "ok": true,
  "data": {},
  "meta": {}
}
```

### Padrão de resposta de erro

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

### Autenticação

Rotas protegidas usam:

```text
Authorization: Bearer exe_token_gerado
```

O token pode ser criado via CLI:

```powershell
php database/creaté_api_token.php usuario@email.com "Nome do token" 90
```

O terceiro parâmetro define validade em dias. Sem ele, o token não expira automaticamente.

## 8. Endpoints disponíveis

| Método | Endpoint | Auth | Admin | Descrição |
| --- | --- | --- | --- | --- |
| GET | `/api/v1` | Não | Não | Índice da API e endpoints disponíveis |
| GET | `/api/v1/health` | Não | Não | Status publico da API |
| GET | `/api/v1/me` | Sim | Não | Dados do usuário autenticado |
| GET | `/api/v1/device-types` | Sim | Não | Lista tipos de dispositivos |
| GET | `/api/v1/companies` | Sim | Não | Lista empresas |
| POST | `/api/v1/companies` | Sim | Sim | Cria empresa |
| GET | `/api/v1/companies/{id}` | Sim | Não | Detalha empresa |
| PUT | `/api/v1/companies/{id}` | Sim | Sim | Atualiza empresa |
| PATCH | `/api/v1/companies/{id}` | Sim | Sim | Atualiza parcialmente empresa |
| DELETE | `/api/v1/companies/{id}` | Sim | Sim | Desativa empresa |
| GET | `/api/v1/companies/{id}/machines` | Sim | Não | Lista dispositivos da empresa |
| POST | `/api/v1/companies/{id}/machines` | Sim | Não | Cria dispositivo |
| GET | `/api/v1/machines/{id}` | Sim | Não | Detalha dispositivo |
| PUT | `/api/v1/machines/{id}` | Sim | Não | Atualiza dispositivo |
| PATCH | `/api/v1/machines/{id}` | Sim | Não | Atualiza parcialmente dispositivo |
| DELETE | `/api/v1/machines/{id}` | Sim | Não | Desativa dispositivo |
| GET | `/api/v1/machines/{id}/photos` | Sim | Não | Lista fotos do dispositivo |
| POST | `/api/v1/machines/{id}/photos` | Sim | Não | Envia fotos do dispositivo |
| DELETE | `/api/v1/machine-photos/{id}` | Sim | Não | Remove foto do dispositivo |

## 9. Paginação e filtros

Listagens da API aceitam páginação:

```text
page=1
per_page=25
```

O retorno inclui metadados:

```json
{
  "pagination": {
    "page": 1,
    "per_page": 25,
    "total": 100,
    "last_page": 4,
    "has_more": true
  }
}
```

Exemplo:

```text
GET /api/v1/companies/1/machines?page=2&per_page=20&status=active
```

## 10. Upload de fotos pela API

Endpoint:

```text
POST /api/v1/machines/{id}/photos
Content-Type: multipart/form-data
```

Campos aceitos:

```text
photos[]=@foto-geral.jpg
network_photo[]=@configuracao-rede.png
photo_type=general
photos_topic[]=equipamento
network_photo_topic[]=ambiente
```

Regras:

- `photos[]`: uma ou mais fotos gerais;
- `network_photo[]`: uma ou mais fotos de configuração de rede;
- `photo_type`: opcional, aceita `general` ou `network_config`;
- `photos_topic[]`: opcional, um tópico por arquivo em `photos[]`, aceita `local`, `ambiente`, `equipamento` ou `outras`;
- `network_photo_topic[]`: opcional, um tópico por arquivo em `network_photo[]`;
- formatos aceitos: JPG, PNG e WEBP;
- limite: 5 MB por arquivo.

## 11. Banco de dados

Tabelas principais:

- `users`: usuários e hash de senha.
- `companies`: empresas cadastradas.
- `machines`: dispositivos e dados técnicos.
- `machine_photos`: fotos dos dispositivos.
- `audit_logs`: logs de auditoria.
- `api_tokens`: tokens de API armazenados como hash.

As exclusoes principais são lógicas. Empresas e dispositivos são marcados como inativos, preservando rastreabilidade e histórico.

## 12. Deploy e operação

O sistema pode rodar em:

- ambiente local com XAMPP;
- hospedagem PHP/MySQL;
- InfinityFree para demonstração ou uso pequeno;
- VPS ou ambiente Docker para producao mais controlada;
- túnel temporario via Cloudflare Tunnel para demonstrações externas.

Para producao, recomenda-se:

- HTTPS ativo;
- `APP_DEBUG=false`;
- `APP_ENV=production`;
- `APP_KEY` forte e guardada com segurança;
- senha administrativa forte;
- backup periodico do banco;
- backup de `public/uploads`;
- restricao de acesso quando possível.

## 13. Scripts importantes

Gerar chave da aplicação:

```powershell
php database/generate_app_key.php
```

Criar usuário administrador:

```powershell
php database/seed_admin.php
```

Aplicar migrações principais:

```powershell
php database/apply_audit_migration.php
php database/apply_credential_crypto_migration.php
```

Criar token de API:

```powershell
php database/creaté_api_token.php usuario@email.com "Integração interna" 90
```

Subir localmente:

```powershell
php -S localhost:8000 -t public
```

## 14. Pontos de aténcao

- O túnel Cloudflare rápido e útil para demonstração, mas não deve ser tratado como hospedagem definitiva.
- A `APP_KEY` e crítica: precisa ser guardada e não pode ser trocada sem planejamento.
- Senhas fracas em usuários administrativos aumentam risco, mesmo com hash no banco.
- InfinityFree pode servir para prova de conceito, mas producao idealmente deve usar uma hospedagem mais controlada.
- Backups ainda precisam ser definidos como rotina operacional.

## 15. Proximos passos recomendados

- Implementar tela administrativa para criar e gerenciar usuários.
- Adicionar recuperação/troca de senha com fluxo seguro.
- Criar niveis de permissao alem de administrador.
- Adicionar logs de exportação.
- Criar backup automatizado do banco e uploads.
- Melhorar documentação OpenAPI/Swagger da API.
- Adicionar testes automatizados para API e validações principais.
- Preparar deploy definitivo em VPS ou ambiente com CI/CD.

## 16. Conclusao

O projeto já possui uma base funcional para inventario de TI, com interface web, auditoria, uploads, API versionada e medidas iniciais de segurança. A arquitetura está simples e organizada, permitindo evolução gradual sem grande complexidade operacional.

Para apresentação ao gestor, o ponto central e que o sistema já atende ao fluxo operacional principal e possui fundação para crescer com mais controle, segurança e integrações.
