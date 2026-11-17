# EXE Kickoff · Front-end prototype

Protótipo independente, navegável e sem integração com a aplicação PHP atual. Todos os dados exibidos são fictícios e as ações são simuladas no navegador.

## Executar

Na raiz do repositório:

```powershell
powershell -ExecutionPolicy Bypass -File .\frontend-prototype\scripts\start_prototype.ps1
```

Depois, abra `http://localhost:4173/`. Também é possível servir esta pasta com qualquer servidor HTTP estático, por exemplo `php -S localhost:4173 -t frontend-prototype`.

## Rotas do protótipo

- `index.html` — dashboard e visão geral
- `login.html` — autenticação
- `companies.html`, `company-form.html`, `company-detail.html` — empresas
- `devices.html`, `machine-form.html`, `machine-detail.html` — dispositivos
- `vault.html` — cofre de senhas
- `audit.html` — auditoria
- `users.html` — usuários
- `settings.html` — configurações
- `sessions.html` — sessões ativas
- `errors.html` — estados 403, 404 e 500
- `design-system.html` — catálogo visual

O protótipo não lê banco, Vault, sessões, APIs ou arquivos da aplicação existente.
