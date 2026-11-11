<?php

declare(strict_types=1);

final class CompanyVaultCategory
{
    public static function selectedIds(int $companyId): array
    {
        $stmt = db()->prepare(
            'SELECT category_id
             FROM company_vault_categories
             WHERE company_id = :company_id
             ORDER BY category_id'
        );
        $stmt->execute(['company_id' => $companyId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'category_id'));
    }

    public static function replaceForCompany(int $companyId, array $categoryIds): void
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), static fn (int $id): bool => $id > 0)));
        $delete = db()->prepare('DELETE FROM company_vault_categories WHERE company_id = :company_id');
        $delete->execute(['company_id' => $companyId]);
        if (!$categoryIds) return;

        $insert = db()->prepare(
            'INSERT INTO company_vault_categories (company_id, category_id)
             VALUES (:company_id, :category_id)'
        );
        foreach ($categoryIds as $categoryId) {
            $insert->execute(['company_id' => $companyId, 'category_id' => $categoryId]);
        }
    }

    public static function isEnabled(int $companyId, int $rootCategoryId): bool
    {
        $stmt = db()->prepare(
            'SELECT 1
             FROM company_vault_categories
             WHERE company_id = :company_id AND category_id = :category_id
             LIMIT 1'
        );
        $stmt->execute(['company_id' => $companyId, 'category_id' => $rootCategoryId]);

        return (bool) $stmt->fetchColumn();
    }

    public static function allowedCompaniesByRoot(array $companyIds): array
    {
        $companyIds = array_values(array_unique(array_filter(array_map('intval', $companyIds), static fn (int $id): bool => $id > 0)));
        if (!$companyIds) return [];

        $placeholders = [];
        $params = [];
        foreach ($companyIds as $index => $companyId) {
            $key = 'company_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $companyId;
        }
        $stmt = db()->prepare(
            'SELECT company_id, category_id
             FROM company_vault_categories
             WHERE company_id IN (' . implode(', ', $placeholders) . ')'
        );
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['category_id']][] = (int) $row['company_id'];
        }

        return $map;
    }

    public static function requiredGlobalRootIds(int $companyId): array
    {
        $stmt = db()->prepare(
            'SELECT DISTINCT required_root.id
             FROM vault_categories required_root
             WHERE required_root.company_id IS NULL
               AND required_root.parent_id IS NULL
               AND (
                    EXISTS (
                        SELECT 1
                        FROM vault_credentials credential
                        INNER JOIN vault_categories category ON category.id = credential.category_id
                        LEFT JOIN vault_categories parent ON parent.id = category.parent_id
                        WHERE credential.company_id = :credential_company_id
                          AND credential.is_active = 1
                          AND (
                              (category.company_id IS NULL AND COALESCE(category.parent_id, category.id) = required_root.id)
                              OR (category.company_id = :local_company_id AND parent.company_id IS NULL AND parent.id = required_root.id)
                          )
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM vault_categories local_child
                        WHERE local_child.company_id = :category_company_id
                          AND local_child.parent_id = required_root.id
                          AND local_child.is_active = 1
                    )
               )'
        );
        $stmt->execute([
            'credential_company_id' => $companyId,
            'local_company_id' => $companyId,
            'category_company_id' => $companyId,
        ]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }
}
