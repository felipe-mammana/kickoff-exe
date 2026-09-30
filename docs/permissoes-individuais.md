# Permissoes individuais de usuarios

Atualizacao: 30/09/2026.

## Modelo

O nivel Administrador/Editor/Usuario fornece um conjunto inicial no formulario.
As permissoes salvas prevalecem sobre a classe, inclusive para administradores.
As checkboxes ficam abaixo do nivel de acesso, na criacao e edicao.

`models/UserPermission.php` define o catalogo e a verificacao central.
`user_permission_profiles` guarda `permissions_json` e `delegation_json`, por usuario.
Delegacao e o limite do que o operador pode administrar; nao concede acesso aos segredos.
Administradores legados preservam a autoridade de administrar todas as permissoes,
sem receber automaticamente acesso ao cofre. Gestores personalizados so podem
administrar o conjunto delegado na sua criacao ou ultima alteracao de permissoes.

| Area | Operacoes |
| --- | --- |
| Empresas | Visualizar, criar, editar, excluir |
| Dispositivos | Visualizar, criar, editar, excluir, revelar senhas |
| Cofre | Acessar, criar, editar, excluir, revelar, copiar, exportar, configurar categorias |
| Relatorios | Visualizar, exportar |
| Auditoria | Consultar, exportar |
| Sessoes | Visualizar, encerrar |
| Usuarios | Visualizar, criar, editar/redefinir senha, gerenciar permissoes |
| Administracao | Configuracoes globais, backups, restauracao/limpeza |

## Regras aplicadas

- As rotas PHP e a API verificam permissoes no servidor. Esconder botoes e complementar.
- Operacoes de uma area exigem tambem acesso de leitura da area.
- Copiar/exportar senhas exige acessar o cofre e revelar senhas.
- Exportar relatorios exige consultar a origem: empresas, dispositivos ou usuarios.
- Configuracoes pessoais continuam disponiveis ao proprio usuario.
- Criacao de usuario exige `users.create` e `users.permissions`.
- Alterar permissoes exige `users.permissions`; mudar dados pessoais exige `users.edit`.
- Criacao e alteracao de permissoes exigem codigo no e-mail atual ou autenticador
  ja cadastrado. Sem confirmacao, o banco nao recebe a alteracao.
- As verificacoes sao repetidas ao concluir o desafio. O formulario pendente fica
  criptografado na sessao e expira em dez minutos.
- Um operador nao pode editar/redefinir a senha de uma conta acima de sua delegacao.
- Nao e permitido alterar as proprias permissoes; outro gestor deve executar isso.
- O ultimo gestor ativo de permissoes nao pode ser removido/desativado.
- Criacao e mudancas de permissoes usam transacao e auditoria obrigatoria.
- Revogacoes afetam as proximas requisicoes web e de API, sem renovar o token.
- Sessoes sao independentes do cofre. Visualizar nao concede encerrar.
- Historicos em empresas/dispositivos exigem consultar auditoria.
- Restaurar SQL exige, alem de `settings.restore`, gerenciar permissoes e ter
  delegacao integral: importar banco pode alterar contas e controles de acesso.

O controle de encerrar sessoes ainda usa a confirmacao administrativa existente
(`SESSION_ADMIN_PASSWORD_HASH`). Esta entrega nao substitui essa senha por MFA.

## Migracao e publicacao

Publicar o codigo PHP, templates e assets alterados. Nao enviar configuracoes
locais, chaves, sessoes, bancos ou logs ao GitHub. Fazer backup antes da publicacao.
Na raiz do projeto, com o MySQL ligado e configuracao local correta:

```powershell
& C:\xampp\php\php.exe scripts/migrate_user_permissions.php
```

O script adiciona a tabela se necessario e grava os acessos legados de cada conta
sem sobrescrever personalizacoes existentes. Pode ser executado novamente.
O usuario exclusivo do cofre mantem o acesso inicial ao cofre e sessoes.
Exportacao do cofre e nova e nao e concedida automaticamente.
Antes da migracao, contas sem perfil individual usam os padroes legados.

## Exportacao do cofre

`POST /?route=vault.export` exige permissao, cofre desbloqueado, CSRF e senha atual.
Exporta apenas credenciais ativas da empresa escolhida, com limite de 5000.
Gera `.exe-vault` criptografado pelas chaves versionadas locais, sem cache;
a auditoria registra empresa e quantidade, nunca o segredo exportado.
Nao e um arquivo de senhas legivel por outros gerenciadores sem decifragem.

Decifragem administrativa local, com a mesma chave disponivel:

```powershell
& C:\xampp\php\php.exe scripts/decrypt_vault_export.php cofre-1.exe-vault cofre-importacao.json
```

A saida JSON contem segredos em claro. Executar apenas em ambiente protegido,
restringir permissoes do arquivo e remove-lo apos a finalidade autorizada.
O script recusa sobrescrever arquivo existente. Nao enviar essa saida ao GitHub.

## Verificacao

Execucao em 30/09/2026: 236 verificacoes aprovadas, nenhuma falha, incluindo
a migracao em processo novo e os testes de interface no Microsoft Edge.

```powershell
& C:\xampp\php\php.exe -d extension=zip tests/run.php
```

`tests/user_permissions.php` cobre permissoes web/API, elevacao de privilegios,
segundo fator, migracao, cofre e sessoes. Usa banco isolado e SMTP simulado.
`tests/user_permissions_ui.cjs` testa presets, dependencias, selecoes salvas e
layout em 1366, 390 e 320 pixels. Requer Playwright e navegador instalado;
habilitar com `PERMISSIONS_UI_TEST=1` e, para Edge, `SECURITY_BROWSER_CHANNEL=msedge`.

## Limites

Permissoes sao por operacao, nao por empresa ou credencial. Quem recebe leitura de
uma area continua acessando os registros dessa area. Desabilitar copiar controla
o recurso da interface; nao impede copiar manualmente um segredo revelado.
Revogar permissoes nao apaga informacoes ja visualizadas ou exportadas.
Backups e restauracao sao capacidades sensiveis e devem ficar com gestores confiaveis.
Esta entrega nao representa certificacao de seguranca nem substitui um pentest.
