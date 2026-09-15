<?php

declare(strict_types=1);

class VaultCategory
{
    public static function iconOptions(): array
    {
        return [
            'lock' => 'Senha',
            'folder' => 'Pasta',
            'shield' => 'Seguranca',
            'key-round' => 'Chave',
            'user' => 'Usuário',
            'users' => 'Usuários',
            'building-2' => 'Empresa',
            'router' => 'Rede',
            'wifi' => 'Wi-Fi',
            'server' => 'Servidor',
            'database' => 'Banco',
            'cloud' => 'Cloud',
            'globe' => 'Web',
            'mail' => 'E-mail',
            'message-circle' => 'Chat',
            'phone' => 'Telefone',
            'credit-card' => 'Financeiro',
            'landmark' => 'Banco',
            'file-text' => 'Documento',
            'briefcase' => 'Trabalho',
            'monitor' => 'Desktop',
            'laptop' => 'Notebook',
            'smartphone' => 'Celular',
            'printer' => 'Impressora',
            'settings' => 'Sistema',
            'terminal' => 'Terminal',
            'code' => 'Codigo',
            'link' => 'Link',
            'tag' => 'Etiqueta',
            'star' => 'Favorito',
        ];
    }

    public static function allWithCounts(?int $companyId = null): array
    {
        self::ensureCompanyScopeColumn();

        $sql = 'SELECT
                    c.*,
                    parent.name AS parent_name,
                    COUNT(v.id) AS credentials_count,
                    MAX(v.last_revealed_at) AS last_revealed_at
                FROM vault_categories c
                LEFT JOIN vault_categories parent ON parent.id = c.parent_id
                LEFT JOIN vault_credentials v
                    ON v.category_id = c.id
                    AND v.is_active = 1';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND v.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' WHERE c.is_active = 1';
        if ($companyId !== null) {
            $sql .= ' AND (c.company_id IS NULL OR c.company_id = :visible_company_id)';
            $params['visible_company_id'] = $companyId;
        } else {
            $sql .= ' AND c.company_id IS NULL';
        }

        $sql .= '
                  GROUP BY c.id
                  ORDER BY COALESCE(parent.name, c.name), c.parent_id IS NOT NULL, c.name';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function withCountsByParent(?int $parentId, ?int $companyId = null): array
    {
        self::ensureCompanyScopeColumn();

        $sql = 'SELECT
                    c.*,
                    parent.name AS parent_name,
                    COUNT(v.id) AS credentials_count,
                    MAX(v.last_revealed_at) AS last_revealed_at
                FROM vault_categories c
                LEFT JOIN vault_categories parent ON parent.id = c.parent_id
                LEFT JOIN vault_credentials v
                    ON v.category_id = c.id
                    AND v.is_active = 1';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND v.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' WHERE c.is_active = 1';
        if ($companyId !== null) {
            $sql .= ' AND (c.company_id IS NULL OR c.company_id = :visible_company_id)';
            $params['visible_company_id'] = $companyId;
        } else {
            $sql .= ' AND c.company_id IS NULL';
        }

        if ($parentId === null) {
            $sql .= ' AND c.parent_id IS NULL';
        } else {
            $sql .= ' AND c.parent_id = :parent_id';
            $params['parent_id'] = $parentId;
        }

        $sql .= ' GROUP BY c.id ORDER BY c.name';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        self::ensureCompanyScopeColumn();

        $stmt = db()->prepare('SELECT * FROM vault_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        self::ensureCompanyScopeColumn();

        $stmt = db()->prepare('SELECT * FROM vault_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function findGlobalBySlug(string $slug): ?array
    {
        self::ensureCompanyScopeColumn();

        $stmt = db()->prepare('SELECT * FROM vault_categories WHERE slug = :slug AND company_id IS NULL LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function isVisibleForCompany(array $category, int $companyId): bool
    {
        return empty($category['company_id']) || (int) $category['company_id'] === $companyId;
    }

    public static function create(array $data): int
    {
        self::ensureCompanyScopeColumn();
        $data['company_id'] = $data['company_id'] ?? null;

        $stmt = db()->prepare(
            'INSERT INTO vault_categories (
                company_id, parent_id, name, slug, description, icon, is_active, created_by, updated_by
            ) VALUES (
                :company_id, :parent_id, :name, :slug, :description, :icon, :is_active, :created_by, :updated_by
            )'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function updateDefault(int $id, string $name, string $icon, ?int $parentId): void
    {
        self::ensureCompanyScopeColumn();

        $stmt = db()->prepare(
            'UPDATE vault_categories
             SET name = :name, icon = :icon, parent_id = :parent_id, updated_by = :updated_by
             WHERE id = :id AND company_id IS NULL'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'icon' => $icon,
            'parent_id' => $parentId,
            'updated_by' => current_user()['id'] ?? null,
        ]);
    }

    public static function slugExists(string $slug): bool
    {
        self::ensureCompanyScopeColumn();

        $stmt = db()->prepare('SELECT id FROM vault_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return (bool) $stmt->fetch();
    }

    private static function ensureCompanyScopeColumn(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = "vault_categories"
               AND COLUMN_NAME = "company_id"'
        );
        $stmt->execute();

        if ((int) $stmt->fetchColumn() === 0) {
            db()->exec('ALTER TABLE vault_categories ADD company_id INT UNSIGNED NULL AFTER id');
            db()->exec('ALTER TABLE vault_categories ADD INDEX idx_vault_categories_company (company_id)');
            try {
                db()->exec('ALTER TABLE vault_categories ADD CONSTRAINT fk_vault_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE');
            } catch (Throwable $exception) {
                error_log('Could not add vault category company foreign key: ' . $exception->getMessage());
            }
        }

        $checked = true;
    }
}
