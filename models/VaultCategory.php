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
            $sql .= ' AND (
                c.company_id = :visible_company_id
                OR (
                    c.company_id IS NULL
                    AND EXISTS (
                        SELECT 1 FROM company_vault_categories enabled
                        WHERE enabled.company_id = :enabled_company_id
                          AND enabled.category_id = c.id
                    )
                )
            )';
            $params['visible_company_id'] = $companyId;
            $params['enabled_company_id'] = $companyId;
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
            $sql .= ' AND (
                c.company_id = :visible_company_id
                OR (
                    c.company_id IS NULL
                    AND EXISTS (
                        SELECT 1 FROM company_vault_categories enabled
                        WHERE enabled.company_id = :enabled_company_id
                          AND enabled.category_id = c.id
                    )
                )
            )';
            $params['visible_company_id'] = $companyId;
            $params['enabled_company_id'] = $companyId;
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

    public static function selectableForCompanies(array $companyIds): array
    {
        self::ensureCompanyScopeColumn();

        $companyIds = array_values(array_unique(array_filter(array_map('intval', $companyIds), static fn (int $id): bool => $id > 0)));
        $sql = 'SELECT c.*, parent.name AS parent_name, parent.company_id AS parent_company_id, company.name AS company_name
                FROM vault_categories c
                LEFT JOIN vault_categories parent ON parent.id = c.parent_id
                LEFT JOIN companies company ON company.id = c.company_id
                WHERE c.is_active = 1
                  AND (c.parent_id IS NULL OR parent.is_active = 1)
                  AND (c.company_id IS NULL';
        $params = [];

        if ($companyIds) {
            $placeholders = [];
            foreach ($companyIds as $index => $companyId) {
                $key = 'company_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $companyId;
            }
            $sql .= ' OR c.company_id IN (' . implode(', ', $placeholders) . ')';
        }

        $sql .= ') ORDER BY COALESCE(company.name, ""), COALESCE(parent.name, c.name), c.parent_id IS NOT NULL, c.name';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $allowedByCategory = CompanyVaultCategory::allowedCompaniesByCategory($companyIds);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            if ($row['company_id'] !== null) {
                $localCompanyId = (int) $row['company_id'];
                if (!empty($row['parent_id']) && $row['parent_company_id'] === null
                    && !in_array($localCompanyId, $allowedByCategory[(int) $row['parent_id']] ?? [], true)
                ) {
                    continue;
                }
                $row['allowed_company_ids'] = (string) $localCompanyId;
                $rows[] = $row;
                continue;
            }
            $allowedCompanyIds = $allowedByCategory[(int) $row['id']] ?? [];
            if (!empty($row['parent_id'])) {
                $allowedCompanyIds = array_values(array_intersect(
                    $allowedCompanyIds,
                    $allowedByCategory[(int) $row['parent_id']] ?? []
                ));
            }
            if (!$allowedCompanyIds) continue;
            $row['allowed_company_ids'] = implode(',', $allowedCompanyIds);
            $rows[] = $row;
        }

        return $rows;
    }

    public static function settingsTree(): array
    {
        self::ensureCompanyScopeColumn();

        $rows = db()->query(
            'SELECT c.*, parent.name AS parent_name, company.name AS company_name
             FROM vault_categories c
             LEFT JOIN vault_categories parent ON parent.id = c.parent_id
             LEFT JOIN companies company ON company.id = c.company_id
             ORDER BY COALESCE(company.name, ""), c.parent_id IS NOT NULL, COALESCE(parent.name, c.name), c.name'
        )->fetchAll();
        $roots = [];
        $children = [];
        foreach ($rows as $row) {
            if (empty($row['parent_id'])) {
                $row['children'] = [];
                $roots[(int) $row['id']] = $row;
            } else {
                $children[] = $row;
            }
        }
        foreach ($children as $child) {
            $parentId = (int) $child['parent_id'];
            if (isset($roots[$parentId])) {
                $roots[$parentId]['children'][] = $child;
            } else {
                $child['children'] = [];
                $roots[(int) $child['id']] = $child;
            }
        }

        return array_values($roots);
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
        if ($category['company_id'] !== null) {
            return (int) $category['company_id'] === $companyId;
        }

        return CompanyVaultCategory::isEnabled($companyId, (int) $category['id']);
    }

    public static function isSelectableForCompany(array $category, int $companyId): bool
    {
        if (empty($category['is_active']) || !self::isVisibleForCompany($category, $companyId)) {
            return false;
        }

        if (empty($category['parent_id'])) {
            return true;
        }

        $parent = self::find((int) $category['parent_id']);

        return $parent !== null
            && !empty($parent['is_active'])
            && self::isVisibleForCompany($parent, $companyId);
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

    public static function globalRoots(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM vault_categories WHERE company_id IS NULL AND parent_id IS NULL';
        if ($activeOnly) $sql .= ' AND is_active = 1';
        $sql .= ' ORDER BY name';

        return db()->query($sql)->fetchAll();
    }

    public static function globalTree(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM vault_categories WHERE company_id IS NULL';
        if ($activeOnly) $sql .= ' AND is_active = 1';
        $sql .= ' ORDER BY parent_id IS NOT NULL, name';
        $rows = db()->query($sql)->fetchAll();
        $roots = [];
        $children = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === null) {
                $row['children'] = [];
                $roots[(int) $row['id']] = $row;
            } else {
                $children[] = $row;
            }
        }
        foreach ($children as $child) {
            $parentId = (int) $child['parent_id'];
            if (isset($roots[$parentId])) {
                $roots[$parentId]['children'][] = $child;
            }
        }

        return array_values($roots);
    }

    public static function duplicateNameExists(string $name, ?int $companyId): bool
    {
        $sql = 'SELECT 1 FROM vault_categories WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND company_id IS NULL';
        $params = ['name' => $name];
        if ($companyId !== null) {
            $sql = 'SELECT 1 FROM vault_categories
                    WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
                      AND (company_id IS NULL OR company_id = :company_id)';
            $params['company_id'] = $companyId;
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
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
