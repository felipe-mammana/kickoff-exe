# Confirmacao adicional de alteracoes de conta

Quando o 2FA está habilitado, troca de senha, troca de email e substituição do
fator ativo exigem código no email atual ou TOTP do autenticador já cadastrado,
além das verificações de senha existentes. Usuários sem 2FA não são obrigados a
informar um código inexistente durante a troca própria permitida. A desativação
de 2FA continua exigindo uma prova adicional antes de remover a proteção.
Cadastro inicial de 2FA preserva seu fluxo de configuração.
Reset de senha de terceiros e alteracao administrativa de email exigem segundo
fator do administrador que realiza a operacao, nao do usuario afetado.

O desafio dura dez minutos, vincula usuario, fingerprint e payload da operacao.
Payload pendente fica criptografado na sessao. Codigo por email fica em hash;
tentativas limitadas por usuario persistem entre sessoes. Reenvio nao estende o
prazo e possui limites de frequencia. Desafio e consumido antes de aplicar acao.
TOTP reutilizado nessa confirmacao e recusado por 120 segundos. Nao se solicita
a chave secreta do autenticador ao usuario.

Troca de email exige prova no endereco atual ou TOTP e depois codigo no novo
endereco. Falha de entrega nao autoriza a alteracao. Se a pessoa perder ambos os
fatores, nao ha bypass automatico nem recuperacao por esse fluxo.

A rota account.challenge exige sessao, CSRF em POST e no-store. Sucesso/falha sao
auditados sem senha ou codigo. Prova adicional nao torna MFA obrigatorio no login
nem altera permissoes do cofre.
