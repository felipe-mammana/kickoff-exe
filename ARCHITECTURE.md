# Arquitetura — EXE Inventário TI

## Visão geral
MVC próprio em PHP.

```text
Browser/Cliente
     ↓
public/index.php
     ↓
bootstrap
 ┌───┴───────────┐
 Web             API
 ↓                ↓
Controllers     ApiRouter/ApiAuth
 ↓                ↓
Models          ApiV1Controller
 ↓                ↓
MySQL ←──────── Models
 ↓
Views           ApiResponse(JSON)
```

## Bootstrap
Carrega configuração, banco e helpers; garante `storage/`, `storage/sessions/` e uploads. O autoload próprio procura classes em models/controllers/includes.

## Web
Rotas principais: dashboard, companies.*, machines.*, users.*, settings.*, audit.*, export.download.

## Models
`Machine`: tipos, consultas, filtros, estatísticas, CRUD, soft delete, duplicidade e credenciais.
`Company`: empresas.
`MachinePhoto`: fotos.
`AuditLog`: auditoria.
`ApiToken`: tokens.

## Segurança
Sessão `exe_session`, strict mode, HttpOnly, SameSite=Lax, Secure em HTTPS e regeneração após login. Web mutável usa CSRF.

## Upload
Arquivo → validação → finfo/MIME → tipo permitido → nome aleatório → armazenamento → metadata.

## Credenciais
Plaintext → validação → CredentialCrypto → AES-256-GCM → `enc:v1:*` → banco.

## Auditoria
Operação → contexto (usuário/IP/sessão/recurso/antes/depois) → audit_logs.

## Diretriz
Evite duplicar regras entre Web e API. Centralize regra de negócio compartilhada quando possível sem enfraquecer autorização ou segurança.
