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

## Teste web

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
