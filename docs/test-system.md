# Roteiro de teste do sistema

Use este roteiro para testar o sistema inteiro sem depender de dados reais.

## Preparar banco limpo

1. Ligue o MySQL no XAMPP.
2. Crie uma base vazia usando `database/schema_empty.sql`.
3. Rode o seed administrativo para criar apenas o usuário admin:

```powershell
C:\xampp\php\php.exe database\seed_admin.php
```

O arquivo `database/schema_empty.sql` não possui empresas, dispositivos, fotos, logs, tokens ou usuários cadastrados.

## Proteção da conta

Execute `C:\xampp\php\php.exe tests\run.php` para rodar os testes automatizados em uma base separada. O nome da base deve conter `test` e ser diferente da base da aplicação. Os testes de conta usam um servidor SMTP simulado em `127.0.0.1`, sem enviar mensagens reais.

- Desativar a confirmação de senha do cofre exige a senha atual em um modal.
- A troca do próprio e-mail exige senha e código enviado ao novo endereço. O e-mail atual permanece ativo até a confirmação; o código expira em dez minutos e só pode ser usado uma vez.
- O 2FA aceita até cinco tentativas em uma janela de dez minutos por conta. Cancelar o desafio ou trocar de navegador não reinicia esse limite.
- Envios de códigos de segurança têm intervalo de um minuto e limite de cinco em dez minutos por conta, compartilhado entre login e configurações.
- Alterar ou redefinir a senha encerra as sessões, revoga tokens de API e invalida desafios 2FA iniciados com a senha antiga. A alteração da própria senha pelo painel administrativo encaminha para as configurações da conta.
- A migração `database/apply_account_protection_migration.php` cria a tabela de limites. A aplicação também a cria automaticamente no primeiro uso, seguindo o padrão das migrações existentes.

Para incluir a verificação visual dos modais em 1366, 390 e 320 pixels, disponibilize `playwright` no Node.js e execute os testes com `SECURITY_UI_TEST=1`. As capturas ficam em `storage/tmp/security-ui-*.png`.

## Teste web manual

1. Acesse o sistema pelo navegador.
2. Faca login com o usuário admin criado pelo seed.
3. Crie uma empresa de teste.
4. Crie um dispositivo de cada tipo principal.
5. Edite um dispositivo e confira se os dados mudaram.
6. Envie fotos pela galeria e pela camera do celular.
7. Remova uma foto.
8. Desative um dispositivo.
9. Abra logs/auditoria e confira os eventos.
10. Exporte CSV/JSON quando existir dado suficiente.
11. Exporte DOCX pela tela do dashboard e confirme que o arquivo abre com resumo, filtros, categorias e fotos por tópico.

## Teste API

1. Gere um token:

```powershell
C:\xampp\php\php.exe database\creaté_api_token.php admin@empresa.com "Teste API" 90
```

2. Cole o token em `docs/api-v1.http`.
3. Execute as chamadas na ordem:
   - health;
   - usuário atual;
   - criar empresa;
   - criar dispositivo;
   - editar dispositivo;
   - enviar foto;
   - listar fotos;
   - remover foto;
   - desativar dispositivo.

## Limpeza depois dos testes

Para voltar ao zero, recrie o banco usando `database/schema_empty.sql` e rode `database/seed_admin.php` novamente.
