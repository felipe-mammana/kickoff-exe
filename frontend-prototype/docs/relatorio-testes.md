# Relatório de testes do protótipo

## Validações executadas

- Conferência de arquivos e links internos com `rg` e inspeção dos HTML.
- Verificação de que as alterações ficaram isoladas em `frontend-prototype/`.
- Validação de sintaxe JavaScript com Node.js, quando disponível.
- Verificação de sintaxe PHP dos arquivos existentes não aplicável: nenhum PHP da aplicação foi alterado.

## Escopo manual recomendado

- Abrir `index.html`, `login.html`, `companies.html`, `devices.html`, `vault.html`, `audit.html`, `users.html`, `settings.html` e `design-system.html`.
- Testar menu mobile, alternância de tema, filtros, formulários e toasts.
- Verificar viewport de 360px, 768px e desktop.

## Resultado

O ambiente é estático, usa apenas dados fictícios e não depende de banco, Vault ou recursos externos em tempo de execução. A validação de pixel-perfect e contraste com o manual oficial da marca permanece pendente de aprovação institucional.
