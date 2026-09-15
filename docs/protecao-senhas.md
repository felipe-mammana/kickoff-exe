# Protecao de senhas de usuarios

Implementacao: 13/09/2026. Nao implementa senha-mestra ou conhecimento zero.

## Hash

PasswordSecurity seleciona Argon2id quando disponivel: 65536 KiB, tres iteracoes,
uma thread. Medicao local PHP 8.2: 161, 166 e 168 ms por hash. Repetir o benchmark
no servidor de destino e dimensionar memoria considerando logins concorrentes.
Fallback em ambientes sem Argon2id: bcrypt, custo 12.

AuthController usa password_needs_rehash apos verificar a senha. A atualizacao
usa comparacao com o hash anterior no UPDATE para nao sobrescrever reset concorrente.
O fingerprint do desafio 2FA usa o hash atualizado. Rehash nao chama updatePassword,
nao revoga tokens nem exige que senhas antigas atendam a nova politica.

## Novas senhas

Criacao, reset administrativo e troca propria exigem ao menos oito caracteres,
maiuscula, minuscula, digito ASCII e especial nao branco. Limite de 72 bytes evita
truncamento silencioso no fallback bcrypt. Letras Unicode sao aceitas.
Validacao no modelo protege chamadas fora dos formularios; controllers mostram
erro e formularios exibem requisitos. Senhas antigas nao sao rejeitadas no login.

## Tentativas

LoginAttempt considera falhas recentes em janela de 30 minutos por conta e IP.
Conta: cinco falhas. Origem: trinta falhas, inclusive em contas diferentes.
Intervalos: 30, 60, 120, 240, 480 e no maximo 900 segundos desde a ultima falha.
Consultas durante bloqueio nao renovam seu prazo. Nao ha bloqueio permanente.
Um agressor persistente ainda pode prejudicar disponibilidade apos expiracoes;
isto nao substitui protecao de borda contra ataques distribuidos ou concorrentes.

PasswordSecurity::confirm aplica a mesma regra com escopo reauth por usuario.
Usado nas confirmacoes de senha da conta, 2FA e reveal do cofre quando a preferencia
de exigir senha esta habilitada. Nao torna a reautenticacao obrigatoria em rotas
que antes nao a exigiam. Limites anteriores de codigos e envio de email permanecem.

## Auditoria

Falhas e bloqueios de login e confirmacao sao registrados sem senhas, hashes ou
codigos no payload. Eventos de confirmacao entram no filtro de criticidade.
Sao alertas consultaveis nos logs, nao notificacoes automaticas por email ou SIEM.

## Verificacao

`php tests/password_security.php`: politica, hash, compatibilidade e progressao.
`php tests/run.php`: banco isolado de testes e fluxos HTTP, incluindo rehash real,
bloqueio entre origens, prazo que nao se renova e confirmacao de senha.
Os testes nao devem ser apontados ao banco de producao.
