# Inventário de telas

## Confirmadas no código

| Tela/protótipo | Evidência no sistema atual | Estado |
| --- | --- | --- |
| Login | `views/auth/login.php` | concluído |
| Dashboard | `DashboardController` | concluído |
| Empresas: lista, cadastro, detalhe | `views/companies/*` | concluído |
| Dispositivos: lista, cadastro, detalhe | `views/machines/*` | concluído |
| Cofre e importação | `views/vault/*` | concluído como listagem visual |
| Auditoria | `views/audit/index.php` | concluído |
| Usuários | `views/users/index.php` | concluído |
| Sessões | `views/sessions/*` | concluído |
| Configurações | `views/settings/*` | concluído como visão consolidada |
| Erros 403, 404, 500 | `views/errors/*` | concluído |
| Design System | nova tela do protótipo | concluído |

## Pendências justificadas

Os fluxos que dependem diretamente de backend (2FA real, upload e preview de arquivos, lightbox com imagens reais, CRUD completo de usuários e importação do cofre) estão representados visualmente e marcados na [matriz de preservação funcional](matriz-preservacao-funcional.md). Nenhuma pendência altera a aplicação atual.
