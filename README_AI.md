# Instruções para Antigravity / Agentes de IA

Projeto: EXE Inventário TI (`kickoff-exe`).

## Leia primeiro
1. CONTEXT.md
2. ARCHITECTURE.md
3. SECURITY.md
4. DEVELOPMENT.md
5. DATABASE.md
6. API.md
7. código real da tarefa

O código/schema/migrations atuais são a fonte final da verdade.

## Não faça sem solicitação explícita
- reescrever em outro framework/stack;
- remover auditoria, CSRF ou criptografia;
- expor credenciais;
- trocar soft delete por delete físico;
- fazer alteração destrutiva no banco;
- adicionar dependência grande apenas por conveniência.

## Nunca
- logue machine_password/admin_password;
- retorne segredos em APIs comuns;
- versione segredos;
- confie em IDs sem autorização;
- valide upload só pela extensão;
- concatene entrada do usuário em SQL.

## Dívida de segurança prioritária
1. autorização granular;
2. isolamento por empresa;
3. rate limiting;
4. sessão/API/CSRF;
5. descriptografia explícita;
6. hardening;
7. exportações;
8. CSP;
9. monitoramento da auditoria.

## Ao executar uma tarefa
Identifique arquivos → leia implementação → planeje → faça a menor mudança coerente → teste → revise segurança → atualize documentação.

Uma tarefa não está pronta se quebra Web/API/banco, reduz segurança, remove auditoria, introduz segredo, ignora autorização ou deixa documentação relevante incorreta.
