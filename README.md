# Inventario TI

Sistema web simples em PHP e MySQL para cadastro e documentacao de dispositivos da empresa.

Funcionalidades principais:

- Administracao de empresas com padrao de etiqueta e status ativo/inativo.
- Cadastro dinamico de Notebook, CPU / Desktop, Roteador, Access Point, Modem e Impressora.
- Fotos gerais por dispositivo e foto de configuracao de rede para impressoras cabeadas.
- Filtros por empresa, tipo, etiqueta, colaborador, departamento, modelo, status e data de cadastro.
- Auditoria de logins, empresas, dispositivos e fotos.

## Como rodar

Requisitos:

- PHP 7.4 ou superior com `pdo_mysql` e `fileinfo`
- MySQL 5.7 ou superior

1. Crie o banco importando `database/schema.sql` no MySQL.
2. Ajuste usuario e senha em `config/config.php`, se necessario.
3. Inicie o MySQL no XAMPP.
4. Aplique a migracao. Ela pode ser rodada em banco novo ou existente:

```powershell
php database/apply_audit_migration.php
```

5. Crie o usuario inicial:

```powershell
php database/seed_admin.php
```

6. Inicie o servidor local:

```powershell
php -S localhost:8000 -t public
```

Se estiver usando XAMPP e o comando `php` nao for reconhecido no PowerShell, use o caminho completo:

```powershell
cd C:\Users\felip\OneDrive\Desktop\exe-kickoff
C:\xampp\php\php.exe database\apply_audit_migration.php
C:\xampp\php\php.exe database\seed_admin.php
C:\xampp\php\php.exe -S localhost:8000 -t public
```

7. Acesse `http://localhost:8000`.

Login inicial:

- E-mail: `admin@empresa.com`
- Senha: `admin123`
