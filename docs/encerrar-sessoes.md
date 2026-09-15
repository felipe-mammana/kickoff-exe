# Encerrar sessoes

Somente a conta autorizada ao cofre acessa a listagem e a acao. A senha exigida
e exclusiva desta operacao: SESSION_ADMIN_PASSWORD_HASH em config/local.php.
Sem hash valido a operacao fica bloqueada; nao existe senha padrao.

Para gerar o hash sem mostrar a senha digitada, execute no PowerShell na raiz:

```powershell
$secure = Read-Host 'Senha administrativa de sessoes' -AsSecureString
$credential = [System.Net.NetworkCredential]::new('', $secure)
$credential.Password | & C:\xampp\php\php.exe database/hash_session_admin_password.php
Remove-Variable credential, secure
```

Configure o hash resultante como valor de SESSION_ADMIN_PASSWORD_HASH no local.php,
entre aspas simples. Nao use a senha em texto puro, nao envie pelo chat e nao
versione o local.php. Exemplo estrutural: 'SESSION_ADMIN_PASSWORD_HASH' => '<hash>'.

Na lista, o icone Encerrar sessao abre confirmacao com senha. CSRF e exigido,
ha limite de cinco tentativas por operador em dez minutos e auditoria obrigatoria.
A confirmacao expira em cinco minutos e vincula a sessao escolhida. Uma sessao
substituida por novo login nao e encerrada pela confirmacao antiga.

O navegador revogado perde acesso na proxima requisicao; nao ha apagamento remoto
de conteudo ja recebido. A operacao nao revoga tokens de API nem desafios de login
2FA ainda nao concluidos. Quem souber a senha ainda precisa estar autenticado na
conta autorizada do cofre. A auditoria identifica essa conta, nao quem compartilha
a senha administrativa.
