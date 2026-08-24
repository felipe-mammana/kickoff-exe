# Documentacao do Projeto EXE Inventario TI

## 1. Resumo executivo

O EXE Inventario TI e um sistema web interno para cadastro, organizacao e auditoria de ativos de tecnologia. A aplicacao centraliza empresas, dispositivos, fotos, historico de alteracoes, usuarios administrativos e uma API versionada para integracoes.

O objetivo principal e substituir controles manuais ou planilhas por uma base unica, rastreavel e com criterios de seguranca basicos para operacao interna.

## 2. Objetivos do sistema

- Centralizar o inventario de dispositivos de TI por empresa.
- Registrar notebooks, desktops, roteadores, access points, modems, impressoras e outros ativos.
- Guardar fotos e evidencias dos equipamentos.
- Manter historico auditavel de logins, cadastros, alteracoes e exclusoes logicas.
- Disponibilizar API REST versionada para consultas e integracoes futuras.
- Melhorar a seguranca no armazenamento de senhas e tokens.

## 3. Tecnologias utilizadas

- Backend: PHP.
- Banco de dados: MySQL/MariaDB.
- Frontend: HTML, CSS e JavaScript sem dependencia pesada de framework.
- Servidor local: XAMPP ou servidor embutido do PHP.
- Deploy simples: hospedagem PHP/MySQL, como InfinityFree, ou tunel temporario via Cloudflare Tunnel.

## 4. Principais funcionalidades

### Empresas

- Cadastro de empresas.
- Edicao de dados.
- Status ativo/inativo.
- Padrao de etiqueta por empresa.
- Exportacao de dados.

### Dispositivos

- Cadastro por tipo de equipamento.
- Campos dinamicos conforme tipo:
  - notebook;
  - CPU / desktop;
  - roteador;
  - access point;
  - modem;
  - impressora;
  - outros.
- Checklist operacional, incluindo TFlux, antivirus e solicitante.
- Senhas tecnicas protegidas por criptografia.
- Upload de fotos gerais e fotos de configuracao de rede.
- Visualizacao detalhada do dispositivo.
- Desativacao logica, preservando historico.

### Auditoria

- Registro de eventos relevantes do sistema.
- Historico de login com sucesso e falha.
- Registro de criacao, edicao e desativacao de empresas e dispositivos.
- Registro de upload e remocao de fotos.
- Filtros por usuario, empresa, dispositivo, acao e periodo.
- Exportacao de logs em CSV e JSON.
- Modal para visualizar alteracoes em JSON sem expandir a tabela.

### Usuarios

- Login autenticado.
- Perfil administrador.
- Controle basico de permissao para rotas administrativas e API.

## 5. Arquitetura do projeto

O projeto foi organizado em camadas simples para facilitar manutencao:

```text
public/       Entrada publica da aplicacao, assets e uploads
config/       Configuracoes, rotas da API e variaveis locais
controllers/  Regras de fluxo das telas e endpoints
models/       Acesso ao banco e persistencia
views/        Templates PHP da interface
includes/     Bootstrap, helpers, sessao e seguranca
database/     Schema, migracoes e scripts auxiliares
docs/         Documentacao e arquivos de teste
storage/      Sessoes e arquivos internos
```

O arquivo publico principal e `public/index.php`. As requisicoes web e de API passam por ele e sao direcionadas aos controllers correspondentes.

## 6. Seguranca implementada

### Senhas de usuarios

As senhas de login dos usuarios nao sao salvas em texto puro. Elas sao armazenadas usando `password_hash`, recurso nativo do PHP proprio para senhas.

Na autenticacao, o sistema utiliza `password_verify`, evitando comparacao manual e reduzindo risco de falhas comuns.

### Credenciais tecnicas dos dispositivos

As senhas cadastradas nos dispositivos, como `machine_password` e `admin_password`, sao protegidas com criptografia reversivel usando AES-256-GCM.

Esse modelo permite:

- armazenar as credenciais criptografadas no banco;
- descriptografar apenas quando o sistema precisa exibir para usuario autorizado;
- evitar que o banco mostre senhas diretamente em caso de consulta simples;
- validar integridade do dado criptografado por meio do modo GCM.

A chave da criptografia fica em `APP_KEY`, definida no arquivo `config/local.php` ou em variavel de ambiente. Essa chave nao deve ir para o Git.

Observacao importante: se a `APP_KEY` for perdida ou trocada depois de haver dados criptografados, as credenciais antigas nao poderao ser abertas corretamente.

### Tokens de API

A API aceita autenticacao por Bearer Token.

O token completo aparece apenas uma vez no momento da criacao. No banco, o sistema armazena somente o hash SHA-256 do token.

Isso reduz o impacto caso alguem consulte diretamente a tabela de tokens, pois o valor real usado para autenticar chamadas nao fica salvo em texto puro.

### CSRF em formularios

Os formularios da interface web usam token CSRF para reduzir risco de envio indevido de acoes a partir de paginas externas.

Esse controle esta presente em operacoes como:

- login;
- logout;
- cadastro e edicao;
- exclusao/desativacao;
- remocao de fotos.

### Sessoes

As sessoes usam configuracoes de seguranca:

- `HttpOnly`, reduzindo acesso por JavaScript;
- `SameSite=Lax`, reduzindo envio indevido de cookies em contexto externo;
- modo estrito de sessao;
- regeneracao de ID apos login.

### Uploads

Os uploads possuem validacoes:

- formatos aceitos: JPG, PNG e WEBP;
- limite de 5 MB por arquivo;
- validacao por MIME type;
- nomes finais gerados com bytes aleatorios;
- bloqueio de execucao de PHP dentro de `public/uploads` via `.htaccess`.

### Auditoria e rastreabilidade

O sistema registra eventos em `audit_logs`, incluindo:

- usuario;
- e-mail;
- tipo de acao;
- tabela afetada;
- registro afetado;
- empresa e dispositivo relacionados;
- IP de origem;
- data/hora;
- dados antigos e novos quando aplicavel.

Campos sensiveis, como senhas de dispositivos, sao mascarados nos logs como `[protegido]`.

## 7. API versionada

A API foi estruturada com versionamento por URL. A versao atual e:

```text
/api/v1
```

### Padrao de resposta de sucesso

```json
{
  "ok": true,
  "data": {},
  "meta": {}
}
```

### Padrao de resposta de erro

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

### Autenticacao

Rotas protegidas usam:

```text
Authorization: Bearer exe_token_gerado
```

O token pode ser criado via CLI:

```powershell
php database/create_api_token.php usuario@email.com "Nome do token" 90
```

O terceiro parametro define validade em dias. Sem ele, o token nao expira automaticamente.

## 8. Endpoints disponiveis

| Metodo | Endpoint | Auth | Admin | Descricao |
| --- | --- | --- | --- | --- |
| GET | `/api/v1` | Nao | Nao | Indice da API e endpoints disponiveis |
| GET | `/api/v1/health` | Nao | Nao | Status publico da API |
| GET | `/api/v1/me` | Sim | Nao | Dados do usuario autenticado |
| GET | `/api/v1/device-types` | Sim | Nao | Lista tipos de dispositivos |
| GET | `/api/v1/companies` | Sim | Nao | Lista empresas |
| POST | `/api/v1/companies` | Sim | Sim | Cria empresa |
| GET | `/api/v1/companies/{id}` | Sim | Nao | Detalha empresa |
| PUT | `/api/v1/companies/{id}` | Sim | Sim | Atualiza empresa |
| PATCH | `/api/v1/companies/{id}` | Sim | Sim | Atualiza parcialmente empresa |
| DELETE | `/api/v1/companies/{id}` | Sim | Sim | Desativa empresa |
| GET | `/api/v1/companies/{id}/machines` | Sim | Nao | Lista dispositivos da empresa |
| POST | `/api/v1/companies/{id}/machines` | Sim | Nao | Cria dispositivo |
| GET | `/api/v1/machines/{id}` | Sim | Nao | Detalha dispositivo |
| PUT | `/api/v1/machines/{id}` | Sim | Nao | Atualiza dispositivo |
| PATCH | `/api/v1/machines/{id}` | Sim | Nao | Atualiza parcialmente dispositivo |
| DELETE | `/api/v1/machines/{id}` | Sim | Nao | Desativa dispositivo |
| GET | `/api/v1/machines/{id}/photos` | Sim | Nao | Lista fotos do dispositivo |
| POST | `/api/v1/machines/{id}/photos` | Sim | Nao | Envia fotos do dispositivo |
| DELETE | `/api/v1/machine-photos/{id}` | Sim | Nao | Remove foto do dispositivo |

## 9. Paginacao e filtros

Listagens da API aceitam paginacao:

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
```

Regras:

- `photos[]`: uma ou mais fotos gerais;
- `network_photo[]`: uma ou mais fotos de configuracao de rede;
- `photo_type`: opcional, aceita `general` ou `network_config`;
- formatos aceitos: JPG, PNG e WEBP;
- limite: 5 MB por arquivo.

## 11. Banco de dados

Tabelas principais:

- `users`: usuarios e hash de senha.
- `companies`: empresas cadastradas.
- `machines`: dispositivos e dados tecnicos.
- `machine_photos`: fotos dos dispositivos.
- `audit_logs`: logs de auditoria.
- `api_tokens`: tokens de API armazenados como hash.

As exclusoes principais sao logicas. Empresas e dispositivos sao marcados como inativos, preservando rastreabilidade e historico.

## 12. Deploy e operacao

O sistema pode rodar em:

- ambiente local com XAMPP;
- hospedagem PHP/MySQL;
- InfinityFree para demonstracao ou uso pequeno;
- VPS ou ambiente Docker para producao mais controlada;
- tunel temporario via Cloudflare Tunnel para demonstracoes externas.

Para producao, recomenda-se:

- HTTPS ativo;
- `APP_DEBUG=false`;
- `APP_ENV=production`;
- `APP_KEY` forte e guardada com seguranca;
- senha administrativa forte;
- backup periodico do banco;
- backup de `public/uploads`;
- restricao de acesso quando possivel.

## 13. Scripts importantes

Gerar chave da aplicacao:

```powershell
php database/generate_app_key.php
```

Criar usuario administrador:

```powershell
php database/seed_admin.php
```

Aplicar migracoes principais:

```powershell
php database/apply_audit_migration.php
php database/apply_credential_crypto_migration.php
```

Criar token de API:

```powershell
php database/create_api_token.php usuario@email.com "Integracao interna" 90
```

Subir localmente:

```powershell
php -S localhost:8000 -t public
```

## 14. Pontos de atencao

- O tunel Cloudflare rapido e util para demonstracao, mas nao deve ser tratado como hospedagem definitiva.
- A `APP_KEY` e critica: precisa ser guardada e nao pode ser trocada sem planejamento.
- Senhas fracas em usuarios administrativos aumentam risco, mesmo com hash no banco.
- InfinityFree pode servir para prova de conceito, mas producao idealmente deve usar uma hospedagem mais controlada.
- Backups ainda precisam ser definidos como rotina operacional.

## 15. Proximos passos recomendados

- Implementar tela administrativa para criar e gerenciar usuarios.
- Adicionar recuperacao/troca de senha com fluxo seguro.
- Criar niveis de permissao alem de administrador.
- Adicionar logs de exportacao.
- Criar backup automatizado do banco e uploads.
- Melhorar documentacao OpenAPI/Swagger da API.
- Adicionar testes automatizados para API e validacoes principais.
- Preparar deploy definitivo em VPS ou ambiente com CI/CD.

## 16. Conclusao

O projeto ja possui uma base funcional para inventario de TI, com interface web, auditoria, uploads, API versionada e medidas iniciais de seguranca. A arquitetura esta simples e organizada, permitindo evolucao gradual sem grande complexidade operacional.

Para apresentacao ao gestor, o ponto central e que o sistema ja atende ao fluxo operacional principal e possui fundacao para crescer com mais controle, seguranca e integracoes.
