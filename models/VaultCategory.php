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

        $sql .= ' WHERE c.is_active = 1
                  GROUP BY c.id
                  ORDER BY COALESCE(parent.name, c.name), c.parent_id IS NOT NULL, c.name';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function withCountsByParent(?int $parentId, ?int $companyId = null): array
    {
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
        $stmt = db()->prepare('SELECT * FROM vault_categories WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO vault_categories (
                parent_id, name, slug, description, icon, is_active, created_by, updated_by
            ) VALUES (
                :parent_id, :name, :slug, :description, :icon, :is_active, :created_by, :updated_by
            )'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function slugExists(string $slug): bool
    {
        $stmt = db()->prepare('SELECT id FROM vault_categories WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return (bool) $stmt->fetch();
    }
}
