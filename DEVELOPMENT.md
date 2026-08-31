# Desenvolvimento — EXE Inventário TI

## Antes de alterar
Leia CONTEXT.md, ARCHITECTURE.md e SECURITY.md; depois abra os arquivos reais, schema e migrations relacionados.

## Configuração
Segredos nunca devem ir ao Git. Produção deve usar APP_ENV=production, APP_DEBUG=false, APP_KEY forte, usuário MySQL dedicado e HTTPS.

## Banco
Mudança de schema exige migration e revisão de Models, API, exportadores, auditoria e documentação.

## Regras
- válidar no servidor;
- PDO parametrizado;
- escapar saída HTML;
- CSRF em ações Web;
- não registrar/expor segredos;
- uploads não executáveis;
- preservar soft delete salvo decisão explícita;
- autorização sempre no backend.

## Testes mínimos
CRUD: sucesso, inexistente, inválido, duplicidade, sem permissão.
Auth: sessão válida/inválida, usuário/admin.
API: sem auth, token válido/inválido, método errado, JSON inválido, autorização.
Upload: JPG/PNG/WEBP, MIME falso, excesso de tamanho, remoção.
Segurança: CSRF, IDOR, acesso entre empresas, credenciais, exportação e rate limiting.

## Testes automatizados
Com o MySQL ligado no XAMPP, rode:

```powershell
C:\xampp\php\php.exe tests\run.php
```

O runner cria um banco isolado `inventario_ti_test`, importa `database/schema_empty.sql`, válida regras críticas e remove o banco ao terminar.

Atualize os documentos quando mudar endpoint, schema, regra, permissão, segurança ou instalação.
