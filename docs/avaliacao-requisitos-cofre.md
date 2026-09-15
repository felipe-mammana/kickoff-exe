# Avaliacao dos requisitos de seguranca do cofre

Data: 11/09/2026. Escopo: comparacao dos 14 grupos enviados pelo usuario com o
codigo local e a arquitetura conhecida. Nao e pentest, certificacao nem parecer
juridico. Nenhuma configuracao de producao foi alterada durante a analise.

## Conclusao executiva

O EXE possui protecoes relevantes de uma aplicacao administrativa, mas ainda nao
deve ser considerado um gerenciador corporativo maduro de segredos de clientes.
Criptografia de campos e autenticacao nao resolvem isolamento, recuperacao,
backups, administracao de chaves e operacao segura.

Recomendacao: manter o cofre com dados ficticios ate cumprir os criterios de
liberacao abaixo. A conta exclusiva criada recentemente e uma restricao de
funcionalidade, nao um ambiente separado nem uma barreira contra administradores
que podem redefinir a senha dessa conta ou acessar backups/servidor.

Legenda: parcial = existe parte do controle, mas nao atende o grupo inteiro;
ausente = nao identificado no codigo; nao verificado = depende de operacao/infra.
Nao atribuo percentual de seguranca: a criticidade dos itens nao e uniforme.

## 1. Criptografia adequada — parcial

Existe: AES-256-GCM em includes/CredentialCrypto.php, IV aleatorio de 12 bytes,
tag de autenticacao e criptografia individual do campo secret_value do cofre.
Senhas de equipamentos, segredo TOTP e tokens Microsoft tambem usam criptografia.
Senhas de login usam password_hash(PASSWORD_DEFAULT), nao texto puro.

Lacunas: titulo, usuario, URL, observacoes e campos personalizados nao recebem
automaticamente essa criptografia de campo. Nao inserir segredos em observacoes
ou campos customizados supondo que sao equivalentes ao campo senha. Revisar tambem
os valores desses campos enviados a auditoria. Arquivo SQL/ZIP completo nao e
criptografado pelo exportador. TLS e disco/banco criptografado dependem do deploy,
nao foram medidos. Localhost HTTP nao comprova TLS publico.

Curto: classificar campos sensiveis, impedir segredos indevidos nos logs, exigir
HTTPS publico e criptografar backups. Medio: migracao versionada de campos
sensiveis com leitura compativel e testes. AES-GCM ja e apropriado; trocar por
XChaCha20 so por nome nao melhora o risco principal.

## 2. Senha-mestra — ausente; senha de login protegida parcialmente

Nao existe senha-mestra separada nem chave derivada dela. APP_KEY permite ao
servidor descriptografar registros. SHA-256 sobre APP_KEY nao e KDF de senha
humana, mas a chave atual deve ser aleatoria; nao confundir os dois usos.

Existe: hash de login e limite fixo de falhas em LoginAttempt. PASSWORD_DEFAULT
nao configura explicitamente Argon2id; no ambiente PHP 8.2 observado usa bcrypt.
Nao encontrei bloqueio progressivo, atrasos graduais ou alertas automaticos.
A senha escolhida para a conta fake e curta e conhecida nesta conversa: nao
deve ser usada em ambiente publico ou com segredos reais.

Curto: senha longa, bloqueio de senhas comuns e limitacao de reautenticacao.
Medio: Argon2id quando suportado, benchmark e password_needs_rehash no login.
Longo: decidir arquitetura de conhecimento zero antes de criar senha-mestra.
Somente adicionar outro campo de senha nao transforma a arquitetura.

## 3. MFA — parcial

Existe: TOTP de 30 segundos em TwoFactorAuth, segredo criptografado em User;
codigos por email, limites de envio/verificacao e confirmacao para desativar.
Ausente: obrigatoriedade por perfil/cofre, FIDO2/WebAuthn/passkeys. SMS nao e usado.
Email nao fornece resistencia a phishing equivalente a WebAuthn.

Curto: MFA obrigatorio para administradores e conta do cofre, inclusive fluxos
de recuperacao; impedir caminhos alternativos mais fracos de contornar a politica.
Medio: passkeys/chaves fisicas com biblioteca madura, testes de enrolamento,
revogacao e recuperacao. Nao implementar criptografia WebAuthn do zero.

## 4. Controle de acesso — parcial e limitado para uso corporativo

Existe: admin/editor/viewer no sistema, guards no backend. Cofre agora utiliza
require_vault_access e ID salvo em vault_exclusive_user_id: apenas a conta
designada acessa rotas e configuracoes. Ela recebeu viewer no resto do sistema,
mas permissoes amplas dentro do cofre; nao e somente leitura no cofre.

Lacunas: nao ha matriz por empresa/equipe/pasta/registro nem papeis gestor/auditor.
Nao existe compartilhamento seguro com prazo/revogacao. Uma conta fake usada
por varias pessoas perde atribuicao individual. O ID exclusivo nao impede que
administrador redefina a senha da conta via UserController::resetPassword.
Backups e acesso ao servidor/banco sao outras fronteiras independentes.

Curto: nao compartilhar a conta, separar dados ficticios e explicitar politica de
reset administrativo. Medio: usuarios individuais, permissoes de ler/revelar/
copiar/criar/editar/excluir/exportar por escopo e testes negativos de todas as rotas.
Longo: delegacao controlada e separacao de administracao de identidades e segredos.

## 5. Auditoria — parcial

Existe: AuditLog e eventos de login, alteracoes, resets, criacao/edicao/desativacao
e reveal. Eventos analisados mascaram secret_value com [protegido]. Ha filtros,
retencao e exportacao. Configuracoes do cofre podem tornar logs de reveal opcionais.

Lacunas: nao ha armazenamento imutavel externo nem monitoramento de anomalias.
Logs no mesmo banco podem ser alterados por acesso privilegiado. Nao foi provada
cobertura de todos os eventos citados (recuperacao, leitura administrativa,
compartilhamento, copia efetiva no cliente). Registrar reveal nao prova copia.

Curto: eventos sensiveis obrigatorios, revisao de redacao de todos os campos e
registro de mudanca de permissoes/politicas. Medio: envio a destino externo
append-only, alertas e testes contra adulteracao. Longo: SIEM e revisao operacional.

## 6. Sessoes — parcial

Existe: verificacao de sessao ativa no banco, timeout configuravel, revogacao
apos troca/reset, cookies HttpOnly/SameSite e Secure quando HTTPS reconhecido.
Tokens Graph nao ficam no localStorage; persistem criptografados no servidor.

Lacuna importante: timeout atual usa idade desde o inicio da sessao, nao ultimo
uso. Nao e bloqueio por inatividade. Nao ha desbloqueio independente do cofre.
Encerramento/revogacao existem no modelo de sessao unica, nao uma gestao completa
de varios dispositivos simultaneos.

Curto: timeout ocioso no servidor, bloqueio do cofre e limpeza de segredo no DOM
ao fechar/sair/expirar. Medio: reautenticacao com prazo curto para operacoes
criticas e testes de sessao roubada/revogacao concorrente.

## 7. Vulnerabilidades de aplicacao — protecoes presentes, cobertura nao comprovada

Existe: PDO preparado, escape, CSRF, CSP e validacoes de upload; testes locais.
Esses mecanismos nao comprovam ausencia de SQLi/XSS/IDOR/SSRF/deserializacao.
O reveal do cofre faz password_verify quando configurado, mas nao chama um
rate limiter proprio nesse trecho: limite do login nao cobre esse endpoint.
machines.revealCredential e outro endpoint: nao exige nova senha atualmente.

Curto: proteger todos os endpoints de segredo com rate limit e reautenticacao,
revisar enumeração/erros/logs e criar testes HTTP com contas reais de cada perfil.
Medio: SAST, analise de dependencias/segredos, DAST e pentest independente com
evidencias reproduziveis. Retestar correcoes antes de declarar achado resolvido.

Semgrep nao iniciou nesta analise: falha local CertOpenSystemStore returned NULL.
Portanto nao houve scan Semgrep concluido nem resultado limpo dessa ferramenta.

## 8. Backup — parcial, prioridade alta

Existe: dump SQL, ZIP com SQL/fotos/anexos e importacao SQL em DatabaseMaintenance.
Ausente no exportador: criptografia integral do arquivo. Nao foram comprovados
retencao externa, copia geografica, imutabilidade, alarmes ou restauracao periodica.
"Banco limpo" nao e anonimo: preserva dados de negocio e pode conter tokens Graph
criptografados em app_settings. ZIP nao substitui copia segura da APP_KEY.

Curto: backup criptografado fora da raiz publica, chave separada, controle de
acesso e primeiro ensaio de restauracao. Definir RPO (perda tolerada) e RTO
(prazo de recuperacao). Medio: copias externas/imutaveis, rotacao e ensaios periodicos.

## 9. Chaves — parcial

Existe: APP_KEY fora do codigo versionado em configuracao/ambiente e segredos
Microsoft em arquivo privado ignorado pelo Git. Ignorar no Git nao e KMS nem ACL.
Nao ha rotacao versionada, envelope encryption, KMS/HSM ou revogacao automatizada.
Trocar APP_KEY sem migrar dados torna credenciais inacessiveis.

Curto: inventario de chaves, permissoes de arquivo, custodia separada e procedimento
de perda/comprometimento. Medio: KMS/gerenciador de segredos, chaves de dados
versionadas e migracao gradual. Longo: HSM se risco/regulacao justificar.

## 10. Recuperacao — basica, insuficiente para cofre de alta garantia

Existe: reset administrativo de senha com invalidacao de sessoes/tokens.
Nao existe senha-mestra a recuperar. Ausentes: codigos de recuperacao de uso
unico, dupla aprovacao e chave de emergencia do usuario. Reset de conta nao
equivale a recuperacao segura de um cofre com conhecimento zero.

Curto: procedimento documentado, identidade verificada, auditoria e alerta.
Medio: codigos de recuperacao armazenados por hash, MFA no reset e aprovacao
adicional conforme risco. Longo: recuperacao criptografica coerente com arquitetura.

## 11. Privacidade/LGPD — nao comprovada como programa

Existem controles tecnicos e retencao de logs, mas isso nao comprova conformidade.
Nao identifiquei programa completo de finalidade/base legal, minimizacao, registro
de tratamento, atendimento de titulares, descarte, contratos e responsabilidades.
Relatorios, observacoes, anexos e logs tambem podem conter dados pessoais.

Curto: inventariar dados/finalidades, restringir segredos em campos livres e
definir responsaveis com a empresa. Medio: politica de retencao/descarte e fluxo
de direitos/incidentes validados pelo encarregado/juridico. Nao prometer conformidade
LGPD apenas com codigo ou checklist; obrigacoes dependem do contexto.

## 12. Monitoramento e incidentes — ausente como operacao automatizada

Logs existem, mas nao encontrei motor de alertas para exportacoes em massa,
dispositivo/local incomum, novos admins, horarios atipicos ou desativacao de logs.
Nao foi demonstrado plano ensaiado de contencao, evidencias e comunicacao.

Curto: runbook de bloquear conta/revogar sessao, preservar evidencias e acionar
responsaveis; alertas de falhas repetidas e alteracoes administrativas.
Medio: correlacao externa, alertas de volume e simulacoes. Longo: resposta gerenciada
e monitoramento continuo conforme criticidade.

## 13. Infraestrutura — nao verificada; isolamento atual insuficiente

Contexto conhecido: Windows/XAMPP e localhost/tunel para testes. Nao auditei firewall,
patches, portas, criptografia de disco, TLS negociado, disponibilidade ou DDoS.
Criar login exclusivo nao separou desenvolvimento/producao: mesmo banco e APP_KEY.

Curto: base separada com dados sinteticos, servidor HTTPS adequado e banco privado.
Medio: homologacao/producao separadas, contas de servico minimas, monitoramento,
backup externo e acesso administrativo MFA. Longo: redundancia se RTO exigir.

## 14. Desenvolvimento seguro — parcial

Existe: testes de backend/HTTP e UI, documentacao e revisoes pontuais.
Ausente ou nao comprovado: pipeline bloqueante, scanner funcionando continuamente,
pentest independente concluido, revisao criptografica e ensaios completos de
retencao/restauracao. Testes com stubs nao substituem testes reais de autorizacao.

Curto: threat model e suite de regressao para acesso/reveal/reset/backup.
Medio: CI com scans/testes, revisao de codigo e pentest/reteste antes de liberar
clientes reais. Longo: revisoes recorrentes e programa formal de vulnerabilidades.

## Ajustes de interpretacao do checklist

- Salt e para hash/KDF de senha; AES-GCM usa nonce/IV, nao sal de senha por campo.
  Os IVs aleatorios existentes atendem parte dessa necessidade.
- Senha do login precisa de hash; senha guardada no cofre precisa ser recuperada,
  portanto usa criptografia reversivel. Hash Argon2id nao substitui AES do cofre.
- TLS 1.2 bem configurado pode ser aceitavel; TLS 1.3 e preferivel, mas o numero
  sozinho nao garante seguranca. Verificar configuracao e cadeia ate o backend.
- Conhecimento zero e decisao de arquitetura, nao requisito universal. Sistema
  atual e confianca no servidor. Para evitar que servidor veja senha-mestra,
  derivacao/desbloqueio precisam ocorrer no cliente com projeto especifico.
- Logs locais, KMS ou MFA isoladamente nao tornam seguro um fluxo de recuperacao fraco.

## Plano por prazo

Estimativas indicativas para equipe pequena, sem promessa de calendario. Dependem
de acesso ao ambiente, infraestrutura, homologacao e aprovacao de politicas.

### Curto: 1 a 3 semanas, antes de dados reais

1. Remover uso publico da conta de teste fraca e separar banco de testes.
2. MFA obrigatorio para cofre/admin e revisao do reset administrativo.
3. Rate limit em reveal/reautenticacao, logs obrigatorios e redacao de campos.
4. Bloqueio por inatividade e reautenticacao para operacoes sensiveis.
5. HTTPS/proxy confiavel, APP_KEY protegida e backup criptografado com restauracao.
6. Matriz de testes negativos: outro usuario, admin comum, sessao expirada,
   CSRF ausente, acesso direto, reset, exportacao e importacao.

Criterio de conclusao: evidencias de bloqueios, nenhum segredo indevido nos logs,
restauracao comprovada e contas de teste sem acesso a dados de clientes.

### Medio: 1 a 3 meses

1. ACL por usuario/cliente/pasta/registro e permissoes especificas de exportar/revelar.
2. Argon2id com migracao por login; WebAuthn e recuperacao robusta.
3. Criptografia dos campos classificados, KMS e rotacao versionada.
4. Auditoria externa imutavel, alertas, backups externos e ensaios recorrentes.
5. CI de seguranca, pentest independente/reteste e programa de privacidade.

Criterio: tentativa cruzada entre clientes bloqueada, autorizacao individual
rastreavel e nenhum achado critico/alto aberto sem tratamento aprovado.

### Longo: 3 a 6+ meses, conforme risco

1. Decidir se manter cofre server-side ou redesenhar para conhecimento zero.
2. Compartilhamento seguro/temporario, dupla aprovacao e recuperacao de emergencia.
3. HSM, alta disponibilidade e monitoramento especializado se justificados.
4. Revisao criptografica externa e auditorias periodicas.

Antes de redesenhar criptografia, comparar custo/risco com integrar um gerenciador
de segredos especializado. Nao e necessario construir cada mecanismo internamente.

## Portao de liberacao

Nao liberar segredos reais somente porque a fase curta terminou. Exigir uma
avaliacao documentada: identidade individual, MFA, autorizacao por escopo necessario,
backup testado, monitoramento e recuperacao, infraestrutura revisada e testes
independentes proporcionais ao risco. Se nao houver isolamento por cliente,
limitar explicitamente o uso a um contexto autorizado e nao prometer multi-tenancy.

## Evidencias no repositorio

- includes/CredentialCrypto.php: algoritmo, nonce e dependencia APP_KEY.
- models/User.php: password_hash, segredo TOTP e resets/sessoes.
- includes/helpers.php: sessao e ID exclusivo do cofre.
- controllers/VaultController.php: guards, reveal, senha opcional e logs.
- models/VaultCredential.php: persistencia de metadados/campos personalizados.
- controllers/UserController.php: poder de reset administrativo.
- models/DatabaseMaintenance.php: dumps/ZIP e limites de limpeza.
- includes/MicrosoftMail.php: OAuth, tokens no servidor e dependencia de conta.
- tests/: cobertura parcial, nao certificacao.

## Referencias externas

- [OWASP Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html): Argon2id e distincao entre hash e criptografia.
- [OWASP Key Management](https://cheatsheetseries.owasp.org/cheatsheets/Key_Management_Cheat_Sheet.html): ciclo de vida de chaves.
- [ANPD: comunicacao de incidentes](https://www.gov.br/anpd/pt-br/canais_atendimento/agente-de-tratamento/comunicado-de-incidente-de-seguranca-cis): processo oficial, a validar com responsavel juridico.
