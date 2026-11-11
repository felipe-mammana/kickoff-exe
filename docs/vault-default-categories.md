# Categorias padrão do Cofre

## Origem

As categorias canônicas foram originalmente definidas por `AppSetting::defaultVaultSettings()` no commit `7b2bd6b`. A definição histórica passou a ficar isolada em `VaultCategorySeeder::defaultCategories()`, sem alimentar as opções das telas. São oito categorias principais e duas subcategorias:

- Acessos administrativos (`shield`)
- Bancos de dados (`database`)
- Cloud e hospedagem (`cloud`)
- E-mails e comunicação (`mail`)
- Financeiro (`credit-card`)
- Rede e infraestrutura (`router`)
  - Roteadores (`router`)
  - Wi-Fi (`wifi`)
- Sistemas internos (`settings`)
- Outros (`folder`)

As categorias padrão são globais (`company_id IS NULL`). Categorias criadas para uma empresa continuam vinculadas ao respectivo `company_id` e não são modificadas pelo seeder.

Os campos personalizados de Roteadores e Wi-Fi pertencem à configuração do Cofre em `app_settings`; não são colunas de `vault_categories`.

A apresentação e a seleção operacional de categorias usam diretamente `vault_categories`. A lista histórica em código permanece somente como definição canônica do seeder e como compatibilidade de leitura dos campos personalizados anteriores; ela não gera opções de interface nem autoriza categorias submetidas pelo navegador.

## Restauração em homologação

O comando é exclusivo de CLI, aceita somente `APP_ENV=homologacao`, valida `DB_NAME` e o banco efetivamente selecionado pelo PDO como `kickoff_hml`. Sem argumentos ele executa somente consultas:

```bash
APP_ENV=homologacao php database/seed_vault_categories.php
```

Depois de revisar a simulação e obter autorização para alterar os dados da HML:

```bash
APP_ENV=homologacao php database/seed_vault_categories.php --apply --confirm-hml=kickoff_hml
```

O seeder é transacional e idempotente. Ele cria padrões ausentes, restaura nome, ícone, descrição, hierarquia e ativação dos padrões existentes, e não exclui registros. Credenciais, segredos, operações do Vault e categorias específicas de empresas não são consultados nem alterados.

## Causa da perda após reset

O schema cria `vault_categories` vazia. Antes desta correção, a rotina que materializava os defaults só era chamada pelo `SettingsController` quando um administrador salvava as configurações do Cofre. Por isso um reset que aplicasse apenas o schema não recriava as categorias.
