<?php

declare(strict_types=1);

class VaultCredential
{
    public static function stats(): array
    {
        $row = db()->query(
            'SELECT
                COUNT(*) AS total,
                SUM(is_active = 1) AS active_total,
                SUM(last_revealed_at IS NOT NULL) AS revealed_total,
                MAX(last_revealed_at) AS last_revealed_at
             FROM vault_credentials'
        )->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active_total' => (int) ($row['active_total'] ?? 0),
            'revealed_total' => (int) ($row['revealed_total'] ?? 0),
            'last_revealed_at' => $row['last_revealed_at'] ?? null,
        ];
    }

    public static function companiesSummary(array $filters = []): array
    {
        $sql =
            'SELECT
                c.id,
                c.name,
                COUNT(v.id) AS credentials_count,
                MAX(v.updated_at) AS last_updated_at
             FROM companies c
             LEFT JOIN vault_credentials v
                ON v.company_id = c.id
                AND v.is_active = 1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= ' AND v.category_id = :join_category_id';
            $params['join_category_id'] = (int) $filters['category_id'];
        }

        $sql .= '
             WHERE c.is_active = 1';

        if (!empty($filters['company_id'])) {
            $sql .= ' AND c.id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND EXISTS (
                SELECT 1
                FROM vault_credentials vf
                WHERE vf.company_id = c.id
                  AND vf.is_active = 1
                  AND vf.category_id = :category_id
            )';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['query']) && ($filters['search_mode'] ?? '') === 'company') {
            $sql .= ' AND c.name LIKE :company_query';
            $params['company_query'] = '%' . $filters['query'] . '%';
        } elseif (!empty($filters['query'])) {
            $sql .= ' AND (
                c.name LIKE :company_query
                OR EXISTS (
                    SELECT 1
                    FROM vault_credentials vn
                    WHERE vn.company_id = c.id
                      AND vn.is_active = 1
                      AND vn.title LIKE :credential_query
                )
            )';
            $params['company_query'] = '%' . $filters['query'] . '%';
            $params['credential_query'] = '%' . $filters['query'] . '%';
        }

        $sql .= ' GROUP BY c.id
                  ORDER BY credentials_count DESC, c.name';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function filtered(array $filters = [], int $limit = 100): array
    {
        $sql =
            'SELECT
                v.id,
                v.category_id,
                v.title,
                v.username,
                v.service_url,
                v.notes,
                v.updated_at,
                c.id AS company_id,
                c.name AS company_name,
                cat.name AS category_name,
                cat.icon AS category_icon
             FROM vault_credentials v
             INNER JOIN companies c ON c.id = v.company_id
             LEFT JOIN vault_categories cat ON cat.id = v.category_id
             WHERE v.is_active = 1
               AND c.is_active = 1';
        $params = [];

        if (!empty($filters['company_id'])) {
            $sql .= ' AND v.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND v.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['query']) && ($filters['search_mode'] ?? '') === 'credential') {
            $sql .= ' AND v.title LIKE :query_title';
            $params['query_title'] = '%' . $filters['query'] . '%';
        } elseif (!empty($filters['query'])) {
            $sql .= ' AND (
                v.title LIKE :query_title
                OR v.username LIKE :query_username
                OR v.service_url LIKE :query_url
                OR cat.name LIKE :query_category
                OR c.name LIKE :query_company
            )';
            $query = '%' . $filters['query'] . '%';
            $params['query_title'] = $query;
            $params['query_username'] = $query;
            $params['query_url'] = $query;
            $params['query_category'] = $query;
            $params['query_company'] = $query;
        }

        $sql .= ' ORDER BY c.name, cat.name, v.title LIMIT :limit';

        $stmt = db()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(1, min($limit, 300)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT
                v.*,
                c.name AS company_name,
                cat.name AS category_name,
                cat.icon AS category_icon
             FROM vault_credentials v
             INNER JOIN companies c ON c.id = v.company_id
             LEFT JOIN vault_categories cat ON cat.id = v.category_id
             WHERE v.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $credential = $stmt->fetch();

        return $credential ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO vault_credentials (
                company_id, category_id, title, service_url, username, secret_value,
                notes, is_active, created_by, updated_by
            ) VALUES (
                :company_id, :category_id, :title, :service_url, :username, :secret_value,
                :notes, :is_active, :created_by, :updated_by
            )'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = db()->prepare(
            'UPDATE vault_credentials SET
                category_id = :category_id,
                title = :title,
                service_url = :service_url,
                username = :username,
                secret_value = :secret_value,
                notes = :notes,
                is_active = :is_active,
                updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public static function deactivate(int $id, int $userId): void
    {
        $stmt = db()->prepare(
            'UPDATE vault_credentials
             SET is_active = 0, updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    public static function markRevealed(int $id, int $userId): void
    {
        $stmt = db()->prepare(
            'UPDATE vault_credentials
             SET last_revealed_at = CURRENT_TIMESTAMP, updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    public static function countFiltered(array $filters = []): int
    {
        $sql =
            'SELECT COUNT(*)
             FROM vault_credentials v
             INNER JOIN companies c ON c.id = v.company_id
             LEFT JOIN vault_categories cat ON cat.id = v.category_id
             WHERE v.is_active = 1
               AND c.is_active = 1';
        $params = [];

        if (!empty($filters['company_id'])) {
            $sql .= ' AND v.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        if (!empty($filters['category_id'])) {
            $sql .= ' AND v.category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['query'])) {
            $sql .= ' AND (
                v.title LIKE :query_title
                OR v.username LIKE :query_username
                OR v.service_url LIKE :query_url
                OR cat.name LIKE :query_category
                OR c.name LIKE :query_company
            )';
            $query = '%' . $filters['query'] . '%';
            $params['query_title'] = $query;
            $params['query_username'] = $query;
            $params['query_url'] = $query;
            $params['query_category'] = $query;
            $params['query_company'] = $query;
        }

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }


    public static function recent(int $limit = 8): array
    {
        $stmt = db()->prepare(
            'SELECT
                v.id,
                v.title,
                v.username,
                v.service_url,
                v.last_revealed_at,
                v.updated_at,
                c.name AS company_name,
                cat.name AS category_name,
                cat.icon AS category_icon
             FROM vault_credentials v
             INNER JOIN companies c ON c.id = v.company_id
             LEFT JOIN vault_categories cat ON cat.id = v.category_id
             WHERE v.is_active = 1
             ORDER BY v.updated_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
