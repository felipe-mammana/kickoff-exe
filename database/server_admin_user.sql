-- Template para criar um administrador no servidor.
-- Gere o hash localmente com password_hash() ou use database/seed_admin.php.
-- Nao versionar hash real de senha neste arquivo.

INSERT INTO users (name, email, password_hash, is_admin, is_active)
VALUES (
    'Nome do Administrador',
    'admin@example.com',
    'COLE_AQUI_UM_HASH_BCRYPT_GERADO_COM_PASSWORD_HASH',
    1,
    1
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password_hash = VALUES(password_hash),
    is_admin = VALUES(is_admin),
    is_active = VALUES(is_active);
