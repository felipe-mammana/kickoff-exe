# Infraestrutura e fluxo Git do projeto Kickoff

Estas instrucoes sao obrigatorias para qualquer trabalho neste repositorio.

## Ambientes

### Homologacao

- Branch Git: `develop`
- Servidor: `SRV-APP`
- IP interno: `10.10.10.10`
- Aplicacao Nginx: `127.0.0.1:8080`
- Banco: `kickoff_hml`, localizado na `SRV-DB` (`10.10.10.20`)
- Diretorio base: `/srv/projetos/kickoff/homologacao/`
- Acesso pelo Windows atualmente via tunel SSH/PuTTY: `127.0.0.1:8888`

### Producao

- Branch Git: `main`
- Servidor: `SRV-APP`
- Aplicacao Nginx: `127.0.0.1:8081`
- Banco: `kickoff_prod`, localizado na `SRV-DB` (`10.10.10.20`)
- Diretorio base: `/srv/projetos/kickoff/producao/`
- Acesso pelo Windows atualmente via tunel SSH/PuTTY: `127.0.0.1:8889`

## Regras obrigatorias para alteracoes

1. Toda nova alteracao deve partir da branch `develop`.
2. Nunca fazer commit diretamente na `main`.
3. Ao concluir uma modificacao, revisar o diff e verificar se nenhum segredo, senha, `APP_KEY`, token ou configuracao privada foi incluido.
4. Nao versionar `config/local.php`.
5. Nao alterar ou substituir credenciais de banco, `APP_KEY` ou outros segredos.
6. Nao incluir arquivos de `storage` que contenham dados gerados pela aplicacao.
7. Validar a sintaxe dos arquivos PHP modificados antes do commit.
8. Quando existirem testes relacionados a alteracao, executa-los antes do commit.
9. Fazer o commit da alteracao somente na branch `develop`, usando mensagem objetiva e descritiva.
10. Fazer push para `origin/develop`.
11. Ao finalizar, informar:
   - resumo das alteracoes;
   - arquivos modificados;
   - testes e validacoes executados;
   - hash e mensagem do commit;
   - se houve alteracao de banco ou necessidade de migration;
   - qualquer risco ou etapa manual necessaria.
12. Nao fazer merge ou push para `main`. A promocao sera realizada manualmente apos os testes em homologacao.

## Banco de dados

Homologacao e producao sao completamente separados. Alteracoes feitas para testes nunca devem apontar a homologacao para `kickoff_prod`.

Se uma modificacao exigir mudanca no banco, criar uma migration ou script versionado e informar claramente que existe uma alteracao de schema. Nao executar alteracoes destrutivas em producao.

## CI/CD

O GitHub Actions possui CI para `develop` e `main`. O codigo deve passar pelas validacoes antes de ser promovido para producao.

Fluxo esperado:

```text
alteracao -> develop -> commit -> push origin/develop -> CI -> teste em homologacao -> aprovacao manual -> main -> producao
```

Ate que o CD esteja completamente configurado, nao assumir que um push atualiza automaticamente os servidores.

## Seguranca

Nunca exibir, copiar para commits ou substituir valores privados encontrados no servidor ou em arquivos locais. Tratar `config/local.php`, senhas, tokens, chaves SSH e `APP_KEY` como segredos.

Se uma tarefa exigir acesso ou alteracao de um segredo, interromper essa parte e informar exatamente o que precisa ser configurado manualmente.
