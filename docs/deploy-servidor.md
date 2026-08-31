# Deploy limpo no servidor

## Arquivos para subir

Use o pacote:

```text
server-upload-clean.zip
```

Ele já está no formato para subir direto na raiz pública do servidor, como `htdocs` ou `public_html`.

O pacote não inclui:

- `config/local.php`;
- sessões locais;
- logs locais;
- uploads reais;
- PDF publico temporario;
- token local de teste.

## Banco de dados novo

No phpMyAdmin ou painel do servidor:

1. Crie um banco vazio.
2. Importe:

```text
database/schema_empty.sql
```

3. Depois importe o usuário administrador inicial:

```text
database/server_admin_user.sql
```

Login inicial:

```text
E-mail: felipe.mammana@exesolucoes.com.br
Senha: exe@123
```

Troque a senha depois do primeiro acesso.

## Configuração do servidor

No servidor, copie:

```text
config/local.server.example.php
```

para:

```text
config/local.php
```

Depois edite:

```php
'APP_URL' => 'https://seu-dominio.com.br',
'APP_KEY' => 'cole_a_app_key_gerada_aqui',
'DB_HOST' => 'host_do_banco',
'DB_NAME' => 'nome_do_banco',
'DB_USER' => 'usuário_do_banco',
'DB_PASS' => 'senha_do_banco',
```

## Gerar APP_KEY

Se tiver acesso a terminal no servidor:

```powershell
php database/generate_app_key.php
```

Se não tiver terminal, gere localmente e copie o valor para o servidor.

Importante: guarde a `APP_KEY`. Ela e usada para abrir as senhas criptografadas dos dispositivos.

## Checklist final

- `index.php` está direto na raiz pública.
- `config/local.php` existe e está preenchido.
- `APP_DEBUG` está `false`.
- Banco importado com `schema_empty.sql`.
- Usuário admin importado com `server_admin_user.sql`.
- Pasta `uploads` existe e contem `.htaccess`.
- Login testado com o usuário admin.
