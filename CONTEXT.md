# EXE Inventário TI — Contexto para Continuidade por IA

## Propósito
Leia este arquivo antes de qualquer alteração relevante. O `kickoff-exe` é uma aplicação PHP/MySQL de inventário de TI para empresas e seus ativos.

> O código atual, schema e migrations são a fonte final da verdade. Esta documentação orienta decisões e deve ser atualizada quando o projeto mudar.

## Objetivos
- cadastrar empresas e equipamentos;
- inventariar notebooks, desktops, roteadores, APs, modems, impressoras e outros;
- registrar etiquetas, hostnames, responsáveis, departamentos e rede;
- armazenar fotos e determinadas credenciais protegidas;
- fornecer filtros, estatísticas, auditoria, API e exportações.

## Stack
- PHP;
- MySQL/MariaDB;
- HTML/CSS/JavaScript;
- MVC próprio;
- PDO/prepared statements;
- API REST;
- AES-256-GCM para credenciais.

## Estrutura
```text
config/
controllers/
database/
docs/
includes/
models/
public/
scripts/
storage/
views/
Frontend-prototipo/
README.md
```

## Fluxo
```text
HTTP
 ↓
public/index.php
 ↓
includes/bootstrap.php
 ├─ Web → Controller → Model → View
 └─ API → ApiRouter → ApiV1Controller → Model → ApiResponse
```

## Tipos
`notebook`, `cpu`, `roteador`, `access_point`, `modem`, `impressora`, `outros`.

## Obrigatoriedade
Notebook/CPU: tag, old_hostname, new_hostname, employee_name, department, computer_model, machine_password.
Roteador: tag, computer_model, admin_user, admin_password, ip_address.
Access Point: install_location, tag, computer_model.
Modem: tag, computer_model, admin_user, admin_password, carrier.
Impressora: tag, brand, computer_model, printer_connection_type.
Outros: tag, computer_model.

## Regras
- `company_id + tag` é único.
- `company_id + new_hostname` é único.
- empresas e máquinas usam soft delete (`is_active=0`).
- `machine_password` e `admin_password` são segredos.
- credenciais não devem aparecer em logs, auditoria, API pública ou exportações comuns.
- uploads devem continuar sendo validados pelo MIME real.

## Credenciais
AES-256-GCM, IV 12 bytes, authentication tag 16 bytes, AAD `exe-kickoff:credential:v1`, prefixo `enc:v1:` e chave derivada da APP_KEY.

## Auditoria
Pode registrar usuário, e-mail, IP, sessão, ação, empresa, máquina, valores anteriores/novos e horário. Segredos devem aparecer como `[protegido]`.

## API
Base `/api/v1`. Autenticação identificada por sessão ou Bearer Token. Paginação padrão 25 e máximo 100. Senhas de máquinas não devem ser retornadas.

## Regras para agentes
1. Leia o código afetado antes de editar.
2. Preserve compatibilidade com dados existentes.
3. Não remova auditoria, CSRF ou criptografia.
4. Nunca registre plaintext de credenciais.
5. Use prepared statements.
6. Valide tudo no backend.
7. Trate autorização como requisito do backend.
8. Use migrations para mudanças de banco.
9. Não reescreva a stack sem solicitação explícita.
10. Leia `SECURITY.md` antes de novos endpoints.

## Ordem de leitura
1. CONTEXT.md
2. ARCHITECTURE.md
3. SECURITY.md
4. DEVELOPMENT.md
5. DATABASE.md
6. API.md
7. README_AI.md
8. código relacionado à tarefa
