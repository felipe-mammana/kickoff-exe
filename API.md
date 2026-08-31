# API — EXE Inventário TI

Base: `/api/v1`.

Componentes: ApiRouter, ApiAuth, ApiRequest, ApiResponse, ApiValidator, ApiV1Controller.

## Autenticação
Sessão web ou Bearer Token foram identificados. Para integrações, preferir Bearer Token.

Rotas mutáveis (`POST`, `PUT`, `PATCH`, `DELETE`) autenticadas por sessão web exigem CSRF via header `X-CSRF-Token` ou campo `csrf_token`. Chamadas com Bearer Token não usam CSRF.

## Respostas
Sucesso: `{"ok":true,"data":{},"meta":{}}`
Erro: `{"ok":false,"error":{"code":"...","message":"...","details":{}}`

Códigos observados: 200, 201, 400, 401, 403, 404, 405, 422.

Paginação: padrão 25, máximo 100; meta contém page, per_page, total, last_page e has_more.

## Endpoints identificados
Públicos:
- GET /api/v1
- GET /api/v1/health

Autenticados:
- GET /api/v1/me
- GET /api/v1/device-types
- GET /api/v1/companies
- GET /api/v1/companies/{id}
- GET /api/v1/companies/{id}/machines
- POST /api/v1/companies/{id}/machines
- GET /api/v1/machines/{id}
- PUT/PATCH/DELETE /api/v1/machines/{id} (admin)
- GET/POST /api/v1/machines/{id}/photos
- DELETE /api/v1/machine-photos/{id} (admin)

Admin:
- POST /api/v1/companies
- PUT/PATCH/DELETE /api/v1/companies/{id}

## Segredos
`machine_password` e `admin_password` não devem integrar recursos públicos.
No backend, credenciais não são descriptografadas por padrão nas consultas de máquinas. Telas comuns devem exibir apenas valores mascarados.

## Novos endpoints
Exigir autorização no backend, autorização por empresa, validação, paginação quando aplicável, auditoria para mutações, ausência de segredos e rate limiting conforme SECURITY.md.
