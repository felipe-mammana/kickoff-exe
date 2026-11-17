# Plano técnico de integração futura

1. Validar tokens, logo e tipografia com a EXE.
2. Extrair o shell aprovado para `views/layout.php`, `views/partials/sidebar.php` e `views/partials/topbar.php`.
3. Mapear os componentes CSS para os templates de empresas e dashboard sem mudar os contratos dos controllers.
4. Migrar formulários de dispositivos preservando nomes de campos, CSRF, upload e validações existentes.
5. Migrar cofre somente após revisar os controles de sessão, permissões, Vault e não exposição de segredos.
6. Executar testes PHP e testes de UI existentes; validar mobile, teclado, contraste e mensagens flash.
7. Promover por etapas em homologação, sem alterar apontamento de banco e sem deploy automático presumido.

## Riscos

- O CSS atual é compartilhado por muitos templates; uma substituição global pode causar regressões.
- O cofre tem regras de segurança próprias e não deve ser tratado como CRUD visual comum.
- Campos e rotas são contratos do backend; mudanças de nomes podem quebrar validações e permissões.
- Os tokens deste protótipo são sugestões, porque a fonte pública não expõe um manual de marca completo.
