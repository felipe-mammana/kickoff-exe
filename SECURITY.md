# Segurança — EXE Inventário TI

Esta e uma análise estática; valide achados com testes antes de classificá-los como exploráveis.

## Proteções existentes
- password_hash/password_verify;
- PDO parametrizado;
- CSRF na Web;
- session_regenerate_id;
- rate limiting de login;
- rate limiting de API;
- HttpOnly/SameSite/Secure;
- AES-256-GCM;
- APP_KEY fora do Git;
- segredos mascarados na auditoria;
- segredos omitidos da API;
- tokens armazenados por hash;
- finfo/MIME em uploads;
- limite de upload e nomes aleatórios;
- proteção contra execução PHP em uploads;
- soft delete e auditoria;
- headers básicos.

## Prioridades

### ALTA — Autorização granular
Usuários autenticados possuem acesso amplo a operações de equipamentos. Implementar autorização no backend por ação/recurso.

Papéis sugeridos: admin, technician, viewer.
Permissões: companies.view/manage, devices.view/creaté/edit/deactivaté, devices.credentials.view, devices.export, audit.view, users.manage, api.manage.

### ALTA — Isolamento por empresa
Se houver múltiplos clientes, criar associação usuário↔empresa e válidar em Dashboard, MachineController, CompanyController, API e ExportController. Nunca confiar em company_id fornecido pelo cliente.

### ALTA — Rate limiting
Login possui limite por IP+e-mail. API possui limite por IP em rotas públicas e por token/usuário em rotas autenticadas. Se necessário, adicionar atraso progressivo e regras diferentes por endpoint sensível.

### MÉDIA/ALTA — Sessão na API e CSRF
Bearer Token e priorizado para API programática. Quando a API usa sessão web em POST/PUT/PATCH/DELETE, o backend exige CSRF.

### MÉDIA — PHP suportado
Não usar PHP 7.4 em produção. Migrar/testar para linha moderna suportada, como PHP 8.2+ ou superior compatível.

### MÉDIA — APP_DEBUG
Produção: `APP_ENV=production`, `APP_DEBUG=false`. Não retornar mensagens internas de exceção.

### MÉDIA — Defaults fracos
Bloquear produção com APP_KEY placeholder, senha administrativa de exemplo ou banco root sem senha.

### MÉDIA — Descriptografia automática
Models de máquina não descriptografam credenciais por padrão. A revelação de credencial na tela do dispositivo e explícita, restrita a administradores e auditada como `credential_viewed`.

### MÉDIA — Exportações
Criar permissão `devices.export` e limitar exportações às empresas autorizadas. Nunca incluir credenciais automaticamente.

### BAIXA/MÉDIA — CSP
Content-Security-Policy aplicada com nonce para script inline essencial, bloqueio de objetos, frame-ancestors e origem própria para scripts/estilos.

### BAIXA/MÉDIA — Auditoria
Falhas não interrompem operação. Adicionar monitoramento, alertas e, se necessário, retry/fila.

### BAIXA — Hardening
Considerar Permissions-Policy, HSTS após HTTPS permanente e Cache-Control em páginas sensíveis.

## Arquitetura recomendada
HTTPS → autenticação (Web sessão+CSRF / API Bearer) → rate limiting → papel → acesso à empresa/recurso → validação → PDO → criptografia → persistência → auditoria/monitoramento.

## Credenciais
Nunca logar, colocar em URL, retornar em listagens ou exportar automaticamente. Descriptografar somente sob autorização; proteger backup da APP_KEY e planejar rotação.

## Checklist
- [ ] PHP suportado
- [ ] APP_DEBUG=false
- [ ] APP_KEY forte
- [ ] usuário DB dedicado
- [ ] HTTPS
- [x] rate limiting de API
- [ ] papéis/permissões
- [ ] isolamento por empresa
- [x] acesso explícito a credenciais
- [x] política sessão/API/CSRF
- [ ] expiração/revogação de tokens
- [x] CSP
- [ ] uploads não executáveis
- [ ] backups testados
- [ ] logs monitorados
- [ ] testes de IDOR/autorização/CSRF/upload/brute force
