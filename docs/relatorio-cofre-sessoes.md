# Relatorio de evolucao: cofre de senhas e sessoes

Data: 14/09/2026.
Escopo: cofre, identidade necessaria ao seu acesso, sessoes, auditoria e recuperacao.
Base: codigo e documentacao locais e resultados registrados das execucoes anteriores.
Este trabalho nao executou novo pentest, teste de carga ou auditoria de infraestrutura.
Nao e certificacao de seguranca, conformidade ou disponibilidade.

## 1. Parecer executivo

O projeto possui uma base funcional de cofre server-side com criptografia, controles
de confirmacao, auditoria e gerenciamento basico de sessoes. Houve avancos concretos
em protecao de dados, reautenticacao, backups e revogacao de acesso.

Status recomendado: candidato a homologacao controlada. Ainda nao ha evidencias
suficientes para recomenda-lo como substituto integral de um cofre corporativo de
clientes. A aprovacao depende de isolamento, identidade individual, recuperacao,
infraestrutura, carga, validacao independente e equivalencia funcional.

O produto atualmente utilizado, suas funcionalidades, quantidade de usuarios,
volume de segredos/anexos e requisitos contratuais nao foram informados. Portanto,
nao e possivel afirmar paridade, estimar capacidade ou fixar uma data de substituicao.

Decisoes vigentes: nao implementar senha-mestra agora; preservar permissoes atuais;
MFA obrigatorio no login adiado. Essas escolhas nao sao tratadas como requisitos
atendidos. Conhecimento zero e uma decisao arquitetural, nao uma garantia existente.

## 2. O que temos e quais sao os limites

| Area | Implementado | Limite ou pendencia |
| --- | --- | --- |
| Organizacao do cofre | Credenciais por empresa, categorias/subcategorias, campos personalizados e gerador de senhas | Equivalencia com o cofre atual nao levantada |
| Criptografia | AES-256-GCM versionado para segredos, observacoes e campos personalizados; chaves por finalidade e rotacao | Titulo, usuario e URL pesquisaveis em claro; servidor possui chaves; sem KMS |
| Login | Argon2id quando disponivel, rehash no login, politica de novas senhas e limites progressivos | Senhas antigas continuam validas; politica de composicao nao verifica senhas vazadas |
| Segundo fator | Email/TOTP; prova adicional nas alteracoes de senha, email e 2FA | Login nao exige MFA universalmente; sem passkeys ou codigos de recuperacao |
| Acesso ao cofre | Guarda de autorizacao no backend e conta designada | Sem ACL por empresa/credencial; conta compartilhada impede atribuicao pessoal |
| Bloqueio do cofre | Senha no desbloqueio; prazo de 20 minutos sem requisicao autorizada; no-store | Nao remove dados ja copiados; navegador e servidor precisam de validacao conjunta em multiplas abas |
| Presenca | Nome, email, IP, navegador, ultima atividade e ultima acao | Online significa atividade recente em 2 minutos; nao prova presenca humana nem identidade do dispositivo |
| Listagem de sessoes | Filtros e link para auditoria por usuario; acesso restrito a conta do cofre | Uma sessao ativa por usuario; sem inventario historico completo de dispositivos |
| Encerramento remoto | Senha administrativa via hash de configuracao, CSRF, limite, auditoria e protecao contra encerrar novo login | Efeito na proxima requisicao; nao revoga tokens API nem desafios 2FA pendentes |
| Auditoria | Eventos no banco e arquivos criptografados; revelacao obrigatoria; mascaramento de campos | Nao imutavel; nao cobre comprovadamente todas as acoes; copia no clipboard nao comprovada |
| Consulta de logs | Administrador confirma senha para ler arquivos protegidos | Senha e do aplicativo, nao da pasta Windows; administrador do host pode apagar arquivos |
| Backups | Downloads criptografados, restauracao SQL isolada e extracao de anexos com hash | Sem rotina externa demonstrada; chaves e arquivos de auditoria exigem copia propria |
| Testes | Ultima suite completa registrada: 190 aprovados | Alteracao posterior do modal validada apenas por sintaxe; nao houve novo teste completo neste relatorio |

O link da listagem para audit.index preserva a permissao administrativa dessa rota.
Uma conta do cofre com perfil viewer pode abrir Sessoes e ainda ser recusada na
auditoria. A politica dessa consulta precisa ser definida antes da homologacao;
nao deve ser resolvida ampliando permissoes silenciosamente.

## 3. Riscos prioritarios

1. Identidade: uma conta exclusiva nao equivale a usuarios individuais. Se for
   compartilhada, o log identifica a conta, nao a pessoa que realizou a acao.
2. Comprometimento do servidor: banco e chaves acessiveis ao mesmo administrador
   permitem recuperar segredos. Criptografia em repouso nao elimina esse risco.
3. Recuperacao: perder email/autenticador ou arquivos de chave pode impedir acesso.
   Reset administrativo nao substitui recuperacao de chaves criptograficas.
4. Disponibilidade: disco cheio, falha no banco ou arquivos de log podem impedir
   revelacoes que exigem auditoria. E necessario monitorar e testar essas falhas.
5. Escopo: acesso amplo autorizado pode ser adequado a uma equipe pequena, mas
   nao deve ser apresentado como isolamento entre clientes ou multi-tenancy.
6. Ambiente: HTTPS, firewall, ACLs, atualizacoes e contas de servico nao foram
   verificados como um conjunto de producao.

## 4. Curto prazo: homologacao segura

Horizonte indicativo: 1 a 3 semanas de trabalho dedicado, sujeito ao tamanho da
equipe, acesso ao ambiente e resultados dos testes. Nao constitui compromisso.

| Entrega proposta | Criterio de aceite |
| --- | --- |
| Inventario do cofre atual | Formatos de exportacao, usuarios, segredos, anexos, permissoes, fluxos criticos e responsaveis documentados |
| Regressao de seguranca e interface | Testar desktop/mobile, abas, expiracao, CSRF, acesso direto, troca de sessao durante encerramento, confirmacao invalida e falha de auditoria |
| Recuperacao de segundo fator | Codigos de uso unico por hash, geracao protegida, revogacao da lista anterior e teste de perda dos fatores |
| Politica de acesso | Resolver consulta da auditoria pelo operador; evitar conta compartilhada para dados reais ou registrar formalmente a limitacao |
| Ambiente de homologacao | Base e chaves separadas, dados sinteticos, HTTPS validado e configuracoes fora da raiz publica |
| Backup operacional | Copia externa criptografada, copia separada de chaves/logs, responsavel definido e restauracao completa em ambiente limpo |
| Plano de incidente | Procedimento para bloquear acesso, revogar sessoes, preservar evidencias, recuperar servico e acionar responsaveis |

MFA obrigatorio para operadores privilegiados permanece uma condicao recomendada
de liberacao, embora sua implementacao tenha sido adiada. Se adiado na estreia,
o risco e as medidas compensatorias precisam ser aprovados formalmente.

Saida da fase: demonstracao reproduzivel dos controles e recuperacao, sem dados
reais em testes e sem achados criticos ou altos sem tratamento aprovado.

## 5. Medio prazo: piloto corporativo e escala

Horizonte indicativo: 1 a 3 meses, apos estabilizar a fase curta.

| Entrega proposta | Criterio de aceite |
| --- | --- |
| Identidade individual | Cada operador identificavel; permissao por funcao e por escopo quando houver necessidade de segregacao |
| Gestao de sessoes | Historico com inicio/fim, expiracao e motivo da revogacao; definir sessao unica ou multiplos dispositivos; revogacao abrangente conforme politica |
| Auditoria externa | Destino independente com retencao protegida, controles de exclusao e testes de entrega, falha e recuperacao |
| Monitoramento | Alertas de falhas repetidas, revelacoes em massa, resets, encerramentos e falta de backup; responsavel e prazo de resposta |
| Custodia de chaves | Separar finalidade de backup/logs das credenciais, inventario, rotacao operacional e avaliar gerenciador de segredos/KMS |
| Escalabilidade | Paginacao no servidor, indices medidos, processamento de backup em lotes/fluxo e teste de carga com metas acordadas |
| Entrega segura | CI com testes e analise de dependencias/segredos, revisao de mudancas e pentest independente com reteste |
| Piloto de migracao | Grupo limitado, amostragem de credenciais/anexos, verificacao de permissoes e retorno ao cofre anterior ensaiado |

Permissoes granulares e MFA sao propostas desta fase, nao autorizacao para mudar
a politica atual. Se todos devem acessar tudo, documentar o escopo compartilhado
e manter identidades individuais em vez de uma credencial coletiva.

Saida da fase: piloto aprovado pelo negocio e seguranca; carga, recuperacao e
alertas demonstrados no ambiente-alvo com usuarios representativos.

## 6. Longo prazo: maturidade e continuidade

Horizonte indicativo: 3 a 6 meses ou mais, conforme criticidade e equipe.

- Passkeys/chaves fisicas e recuperacao com aprovacao adicional para acoes de alto risco.
- Alta disponibilidade e procedimentos de desastre compativeis com metas do negocio.
- Compartilhamento temporario, aprovacao dupla e rotacao automatica de credenciais,
  somente se forem necessarios para substituir o produto atual.
- Revisao criptografica independente e avaliacao de zero-knowledge apenas se houver
  requisito de impedir o servidor de conhecer os segredos.
- Exercicios periodicos de recuperacao/incidente, pentests recorrentes e revisao
  de acessos, retencao e descarte.

Saida da fase: operacao sustentavel, responsabilidades definidas e evidencias
recorrentes; nao apenas conclusao de funcionalidades.

## 7. Requisitos de escalabilidade

Hoje nao ha benchmark que permita declarar capacidade em usuarios ou credenciais.
A presenca e gravada a cada requisicao autenticada verificada; a listagem carrega
todos os usuarios e filtra em PHP, com consulta da ultima acao por usuario. Backups
e recuperacao carregam conteudo em memoria. Logs locais sao escritos de forma
sincrona. Esses pontos devem ser medidos antes de aumentar o volume.

Para varias instancias, sessoes PHP, arquivos locais e locks de manutencao tambem
precisam de coordenacao compartilhada; apenas colocar um balanceador nao resolve.
Nao introduzir Redis, filas ou cluster sem medir a necessidade. Definir como
revelacoes se comportam quando a auditoria externa ou a fila estiver indisponivel.

Metas a acordar antes do teste:
- Usuarios cadastrados e simultaneos; quantidade de segredos e volume de anexos.
- Latencia p95 de listar, pesquisar, desbloquear e revelar sob carga esperada.
- Taxa de erros, consumo de memoria/CPU e concorrencia de hashes Argon2id.
- Crescimento diario dos logs, armazenamento, retenção e janela de backup.
- RPO: perda maxima tolerada de dados. RTO: tempo maximo para recuperar operacao.
- Margem de crescimento e comportamento seguro em sobrecarga.

## 8. Plano para substituir o cofre atual

1. Levantar funcionalidades obrigatorias e comparar com as existentes. Tratar
   integracoes ausentes como pendencias, nao como equivalentes por aproximacao.
2. Exportar por mecanismo autorizado para ambiente protegido; nunca colocar
   exportacao de senhas em repositorio, email, chat ou pasta publica.
3. Preparar migrador com simulacao, mapeamento de categorias/campos, duplicidades,
   anexos e registro de falhas sem conteudo secreto.
4. Ensaiar com dados sinteticos e depois lote piloto autorizado. Comparar contagens,
   integridade de anexos e leitura controlada de amostras; nao imprimir senhas em logs.
5. Definir fonte unica de verdade durante o piloto. Evitar duas bases recebendo
   alteracoes sem reconciliacao e decidir como tratar mudancas apos o corte.
6. Realizar backup, congelar alteracoes conforme janela acordada, migrar e validar.
   Manter o cofre anterior somente leitura pelo periodo aprovado, quando suportado.
7. Ensaiar rollback com preservacao das alteracoes feitas depois da migracao.
8. Desativar o anterior apenas apos aceite, prazo de retorno e descarte controlado
   dos arquivos temporarios. Manter evidencias sem guardar segredos desnecessarios.

### Portao de liberacao

A substituicao so deve ser aprovada quando TODOS os pontos aplicaveis estiverem
atendidos ou tiverem risco residual explicitamente aceito pelo responsavel:

- Paridade dos fluxos obrigatorios comprovada com os usuarios.
- Identidade individual e escopo autorizado definidos; MFA e recuperacao tratados.
- Sem falhas criticas/altas abertas sem tratamento formal.
- HTTPS, banco privado, permissoes de arquivos e custodia de chaves verificados.
- Recuperacao completa em outro ambiente dentro de RPO/RTO acordados.
- Carga e crescimento validados; monitoramento e responsavel de operacao ativos.
- Migracao e rollback ensaiados; plano de suporte e manutencao aprovado.

## 9. Evidencias locais

- [Criptografia versionada](../includes/VersionedCrypto.php), [metadados do cofre](../models/VaultCredential.php).
- [Hash e confirmacao de senha](../includes/PasswordSecurity.php), [desafio de alteracao](../controllers/AccountChallengeController.php).
- [Bloqueio do cofre](../includes/VaultSession.php), [presenca](../models/SessionPresence.php), [encerramento](../controllers/SessionController.php).
- [Auditoria](../models/AuditLog.php), [arquivos protegidos](../includes/ProtectedAuditFiles.php).
- [Backups criptografados](../includes/EncryptedBackup.php), [recuperacao](../includes/BackupRecovery.php).
- [Suite de testes](../tests/run.php), [protecoes operacionais](cofre-protecao-operacional.md).

As evidencias demonstram implementacoes especificas. Nao comprovam que todo o
sistema foi auditado nem que o cofre atual pode ser substituido imediatamente.
