# Categorias do Cofre por empresa

As categorias globais são mantidas em `vault_categories` com `company_id IS NULL`. Cada empresa habilita categorias principais e subcategorias específicas por meio de `company_vault_categories`. Uma subcategoria somente pode ser selecionada quando sua categoria principal também está habilitada.

Categorias criadas dentro da página de uma empresa recebem o `company_id` correspondente e nunca ficam disponíveis para outras empresas. A aplicação recusa nomes que já existam como categoria global ou na própria empresa, inclusive quando o registro existente é uma categoria padrão.

No cadastro e na edição da empresa, a seleção é opcional e hierárquica. Categorias e subcategorias em uso por credenciais ativas não podem ser desabilitadas. Uma categoria principal também permanece protegida quando possui subcategorias específicas daquela empresa.

O formulário de credenciais não apresenta categorias antes da seleção da empresa. Depois da seleção, são exibidas somente:

- categorias globais e subcategorias habilitadas para a empresa;
- categorias exclusivas da empresa.

No cadastro e na edição da credencial, a categoria principal é escolhida primeiro. O campo de subcategoria aparece em seguida e lista somente as subcategorias habilitadas para a empresa e pertencentes à categoria escolhida.

## Migração

A alteração de banco é aditiva e não modifica credenciais ou categorias existentes. O instalador cria a tabela de associação e, somente na primeira execução, habilita todas as categorias e subcategorias globais ativas para as empresas existentes. Instalações da versão anterior recebem as subcategorias das categorias já habilitadas, preservando o comportamento anterior:

```bash
APP_ENV=homologacao php database/apply_company_vault_categories_migration.php
```

O script é exclusivo de CLI e reutiliza a proteção de destino da migração Vault KV v2. Um marcador no comentário da tabela permite concluir com segurança a carga inicial se o DDL tiver sido aplicado e a execução for interrompida. Depois da conclusão, reexecuções preservam as escolhas já realizadas e não recriam vínculos removidos posteriormente.
