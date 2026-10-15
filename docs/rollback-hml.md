# Deploy e rollback da homologacao

Este documento descreve o mecanismo de publicacao da homologacao. Ele nao se
aplica a producao e nao autoriza alteracoes em banco, Vault, rede ou servicos.

## Garantias do mecanismo

- O pacote e extraido primeiro em um diretorio de staging dentro de `releases/`.
- Configuracoes e `storage` continuam compartilhados por links simbolicos.
- A release recebe `.deploy-ready` antes de ficar elegivel para ativacao.
- A troca de `current` usa um link temporario no mesmo diretorio e `mv -T`, de
  modo que o rename seja atomico no mesmo filesystem.
- O alvo anterior e resolvido antes da publicacao e nao e removido.
- Falhas depois da ativacao restauram o alvo anterior com outro rename atomico.
- `EXIT`, `SIGHUP`, `SIGINT` e `SIGTERM` executam a mesma rotina de limpeza.
- Pacote, staging e links temporarios sao removidos em sucesso ou falha.
- Uma release parcial do mesmo SHA e removida antes de uma nova tentativa.
- Uma release completa do mesmo SHA e reutilizada; repetir o job e idempotente.
- Um lock em `.deploy-lock` impede duas publicacoes remotas simultaneas.

`SIGKILL`, desligamento abrupto da VM e falha do filesystem nao podem executar
traps. Nesses casos, use a recuperacao manual abaixo.

## Health check

O deploy consulta `http://127.0.0.1:8080/api/v1/health`, exige HTTP 200 e valida
que o JSON contenha `"ok": true`. Esse endpoint atravessa o bootstrap da
aplicacao, o PHP-FPM e o rate limiter persistido no MySQL; portanto, oferece
cobertura maior que aceitar 200 ou 302 na pagina inicial. A consulta nao altera
schema nem dados funcionais, embora o rate limiter mantenha seus contadores
operacionais normais.

O endpoint continua sujeito ao limite publico. Se monitoramento local passar a
consulta-lo em alta frequencia, deve-se criar futuramente uma rota interna
restrita pelo Nginx, em vez de desabilitar globalmente o rate limiting.

### Observacao de configuracao encontrada na auditoria

Em 2026-10-14, tanto o acesso pelo tunel `127.0.0.1:8888` quanto o dominio HTTPS
de homologacao responderam `"environment":"local"`. O valor esperado para um
ambiente publicado deve ser definido explicitamente pela configuracao privada
de homologacao. Como `APP_DEBUG` usa `APP_ENV === 'local'` como valor padrao,
um administrador deve confirmar manualmente que debug esta desabilitado. O
arquivo privado nao deve ser exibido, copiado ou versionado para fazer essa
correcao.

## Testes isolados

Execute em Linux, sem apontar para a homologacao real:

```bash
bash -n scripts/deploy-hml-release.sh
bash tests/deploy_hml_rollback.sh
```

A suite cria toda a topologia sob um diretorio temporario e cobre:

- pacote invalido antes da ativacao;
- health check falho depois da ativacao;
- deploy bem-sucedido;
- repeticao do mesmo SHA;
- substituicao de release parcial;
- `SIGTERM` depois da troca de `current`.

## Recuperacao manual

Execute como usuario autorizado na SRV-APP. Antes de qualquer mudanca, registre
os alvos e confirme que a release anterior existe:

```bash
BASE=/srv/projetos/kickoff/homologacao
readlink -f "$BASE/current"
find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d -printf '%TY-%Tm-%Td %TH:%TM:%TS %p\n' | sort
```

Escolha explicitamente a release anterior validada. Nao use um glob ou o
resultado de `ls` diretamente. Para restaurar, crie o link no mesmo diretorio e
substitua `current` atomicamente:

```bash
BASE=/srv/projetos/kickoff/homologacao
PREVIOUS=/srv/projetos/kickoff/homologacao/releases/COLOQUE_O_SHA_VALIDADO
test -d "$PREVIOUS" && test -f "$PREVIOUS/public/index.php"
ln -s "$PREVIOUS" "$BASE/.manual-rollback"
mv -Tf "$BASE/.manual-rollback" "$BASE/current"
curl -fsS --max-time 15 http://127.0.0.1:8080/api/v1/health
```

Se um processo terminou com `SIGKILL`, confirme que nao existe deploy ativo
antes de remover o lock residual:

```bash
BASE=/srv/projetos/kickoff/homologacao
ps -ef | grep '[d]eploy-hml-release.sh'
test -d "$BASE/.deploy-lock" && rmdir "$BASE/.deploy-lock"
```

Esses comandos alteram a homologacao real e exigem aprovacao previa.

## Teste final controlado em homologacao

O teste real deve ser agendado e aprovado antes da execucao:

1. Confirmar `current`, HTTP/JSON do health check e ausencia de deploy ativo.
2. Confirmar que a release anterior permanece presente e integra.
3. Publicar um commit de teste cujo pacote seja valido, mas executar o script
   com uma URL de health check controladamente invalida. Nao alterar aplicacao,
   Nginx, PHP-FPM, MySQL, Vault, firewall, DNS ou TLS para provocar a falha.
4. Observar a troca temporaria e a restauracao automatica de `current`.
5. Confirmar que `current` voltou exatamente ao alvo registrado no passo 1.
6. Confirmar health check HTTP 200 com `ok:true` e revisar logs de Nginx e
   PHP-FPM somente leitura.
7. Confirmar ausencia de pacote, staging e links temporarios residuais.
8. Reexecutar o mesmo SHA com health check normal para validar a repeticao.

Uma alternativa ainda mais conservadora e executar o script integralmente sob
`/tmp`, com servidor HTTP falso, usando a suite automatizada. Esse ensaio nao
substitui o teste final do symlink real, mas nao afeta usuarios.
