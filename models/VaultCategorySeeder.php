<?php

declare(strict_types=1);

final class VaultCategorySeeder
{
    private const ROOT_DESCRIPTION = 'Categoria padrão do cofre.';
    private const CHILD_DESCRIPTION = 'Subcategoria padrão do cofre.';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function inspect(array $categories): array
    {
        $this->assertSchema();

        $items = [];
        foreach ($categories as $category) {
            $root = $this->inspectNode($category, null);
            $items[] = $root;

            $parentId = $root['id'];
            foreach ((array) ($category['children'] ?? []) as $child) {
                $items[] = $this->inspectNode($child, $parentId, true);
            }
        }

        return $items;
    }

    public function synchronize(array $categories, ?int $userId = null): array
    {
        $this->assertSchema();
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'items' => []];

        try {
            foreach ($categories as $category) {
                $parent = $this->synchronizeNode($category, null, $userId, $result);
                foreach ((array) ($category['children'] ?? []) as $child) {
                    $this->synchronizeNode($child, (int) $parent['id'], $userId, $result);
                }
            }

            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return $result;
    }

    private function inspectNode(array $category, ?int $parentId, bool $isChild = false): array
    {
        $name = trim((string) ($category['name'] ?? ''));
        $icon = (string) ($category['icon'] ?? 'folder');
        if ($name === '') {
            throw new InvalidArgumentException('Categoria padrão sem nome.');
        }

        if ($parentId === null && $isChild) {
            return ['id' => null, 'name' => $name, 'parent_id' => null, 'status' => 'create'];
        }

        $slug = self::baseSlug(($parentId !== null ? 'sub-' . $parentId . '-' : '') . $name);
        $existing = $this->findGlobal($slug, $name, $parentId);
        if (!$existing) {
            return ['id' => null, 'name' => $name, 'parent_id' => $parentId, 'status' => 'create'];
        }

        $description = $parentId === null ? self::ROOT_DESCRIPTION : self::CHILD_DESCRIPTION;
        $status = $this->requiresUpdate($existing, $name, $icon, $description, $parentId)
            ? 'update'
            : 'unchanged';

        return ['id' => (int) $existing['id'], 'name' => $name, 'parent_id' => $parentId, 'status' => $status];
    }

    private function synchronizeNode(array $category, ?int $parentId, ?int $userId, array &$result): array
    {
        $name = trim((string) ($category['name'] ?? ''));
        $icon = (string) ($category['icon'] ?? 'folder');
        if ($name === '') {
            throw new InvalidArgumentException('Categoria padrão sem nome.');
        }

        $slugBase = self::baseSlug(($parentId !== null ? 'sub-' . $parentId . '-' : '') . $name);
        $description = $parentId === null ? self::ROOT_DESCRIPTION : self::CHILD_DESCRIPTION;
        $existing = $this->findGlobal($slugBase, $name, $parentId);

        if (!$existing) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO vault_categories (
                    company_id, parent_id, name, slug, description, icon, is_active, created_by, updated_by
                 ) VALUES (
                    NULL, :parent_id, :name, :slug, :description, :icon, 1, :created_by, :updated_by
                 )'
            );
            $stmt->execute([
                'parent_id' => $parentId,
                'name' => $name,
                'slug' => $this->uniqueSlug($slugBase),
                'description' => $description,
                'icon' => $icon,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $existing = ['id' => (int) $this->pdo->lastInsertId()];
            $status = 'created';
        } elseif ($this->requiresUpdate($existing, $name, $icon, $description, $parentId)) {
            $stmt = $this->pdo->prepare(
                'UPDATE vault_categories
                 SET parent_id = :parent_id,
                     name = :name,
                     description = :description,
                     icon = :icon,
                     is_active = 1,
                     updated_by = :updated_by
                 WHERE id = :id AND company_id IS NULL'
            );
            $stmt->execute([
                'id' => (int) $existing['id'],
                'parent_id' => $parentId,
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'updated_by' => $userId,
            ]);
            $status = 'updated';
        } else {
            $status = 'unchanged';
        }

        $result[$status]++;
        $result['items'][] = [
            'id' => (int) $existing['id'],
            'name' => $name,
            'parent_id' => $parentId,
            'status' => $status,
        ];

        return $existing;
    }

    private function findGlobal(string $slug, string $name, ?int $parentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM vault_categories
             WHERE company_id IS NULL AND slug = :slug
             LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $category = $stmt->fetch();
        if ($category) {
            return $category;
        }

        $parentCondition = $parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM vault_categories
             WHERE company_id IS NULL
               AND {$parentCondition}
               AND name = :name
             LIMIT 1"
        );
        $params = ['name' => $name];
        if ($parentId !== null) {
            $params['parent_id'] = $parentId;
        }
        $stmt->execute($params);
        $category = $stmt->fetch();

        return $category ?: null;
    }

    private function requiresUpdate(
        array $existing,
        string $name,
        string $icon,
        string $description,
        ?int $parentId
    ): bool {
        $existingParent = $existing['parent_id'] === null ? null : (int) $existing['parent_id'];

        return (string) $existing['name'] !== $name
            || (string) $existing['icon'] !== $icon
            || (string) ($existing['description'] ?? '') !== $description
            || (int) $existing['is_active'] !== 1
            || $existingParent !== $parentId;
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vault_categories WHERE slug = :slug');

        while (true) {
            $stmt->execute(['slug' => $slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $slug = substr($base, 0, 104) . '-' . $suffix;
            $suffix++;
        }
    }

    public static function baseSlug(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii ?: $name), '-'));

        return $base !== '' ? substr($base, 0, 110) : 'categoria';
    }

    private function assertSchema(): void
    {
        $required = ['id', 'company_id', 'parent_id', 'name', 'slug', 'description', 'icon', 'is_active', 'created_by', 'updated_by'];
        $stmt = $this->pdo->prepare(
            'SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => 'vault_categories']);
        $columns = array_map(static fn (array $row): string => (string) $row['COLUMN_NAME'], $stmt->fetchAll());
        $missing = array_values(array_diff($required, $columns));
        if ($missing) {
            throw new RuntimeException('Schema de vault_categories incompatível; colunas ausentes: ' . implode(', ', $missing));
        }
    }
}
