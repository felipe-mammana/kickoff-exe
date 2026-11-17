# Diagnóstico do front-end atual

## Escopo e método

Análise estática do repositório em 16/11/2026, sem alterar os arquivos existentes. O sistema atual é uma aplicação PHP tradicional, com entrada em `public/index.php`, controllers PHP, templates em `views/`, assets em `public/assets/` e persistência MySQL/MariaDB.

## Inventário técnico

- Linguagem e runtime: PHP, com alvo documentado de PHP 8.2 ou superior.
- Persistência: MySQL/MariaDB, com schema e migrações em `database/`.
- Renderização: server-side via templates PHP e `views/layout.php`.
- Front-end atual: CSS em `public/assets/css/app.css`, JavaScript em `public/assets/js/app.js` e `export.js`, ícones gerados pelos helpers PHP.
- Sem framework de front-end separado identificado.
- Segurança e sessão: bootstrap/helpers, CSRF, permissões, 2FA, rate limit, criptografia e HashiCorp Vault no backend.

## Arquitetura visual atual

O layout compartilhado é composto por sidebar, topbar, shell autenticado e shell de autenticação. A navegação é permissionada e inclui Dashboard, Empresas, Cofre, Sessões, Logs do sistema, Usuários e Configurações. O tema claro/escuro já existe no layout atual.

## Rotas e telas confirmadas no código

| Área | Rotas principais | Templates |
| --- | --- | --- |
| Autenticação | `login`, `logout`, `login.2fa.*` | `views/auth/login.php` |
| Dashboard | `dashboard` | `DashboardController` + layout |
| Empresas | `companies.index/create/show/edit/*` | `views/companies/*` |
| Dispositivos | `machines.create/show/edit/*` | `views/machines/*` |
| Auditoria | `audit.index`, `settings.auditFiles` | `views/audit/index.php`, `views/settings/protected-audit.php` |
| Usuários | `users.index/*` | `views/users/index.php` |
| Cofre | `vault.index/show/*`, `vault.import.*` | `views/vault/*` |
| Sessões | `sessions.index/terminate` | `views/sessions/*` |
| Configurações | `settings.*` | `views/settings/*` |
| Erros | 403, 404, 500 | `views/errors/*` |

## Módulos e impactos futuros

O protótipo deve ser tratado como camada visual futura. A integração exigirá mapear cada página para os controllers existentes, preservar nomes de campos, CSRF, permissões, mensagens flash, upload de fotos, exportações e fluxos de cofre. A área do cofre e qualquer dado de credencial não deve receber dados mock em uma integração parcial.

## Limitações da auditoria

Não foi executado banco, Vault, deploy ou autenticação real. Este relatório descreve a estrutura de código e os templates presentes, não o conteúdo de dados de um ambiente.
