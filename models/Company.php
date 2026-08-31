<?php

declare(strict_types=1);

class Company
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT c.*, creator.name AS created_by_name, updater.name AS updated_by_name
                FROM companies c
                LEFT JOIN users creator ON creator.id = c.created_by
                LEFT JOIN users updater ON updater.id = c.updated_by';

        if ($activeOnly) {
            $sql .= ' WHERE c.is_active = 1';
        }

        $sql .= ' ORDER BY c.is_active DESC, c.name';

        return db()->query($sql)->fetchAll();
    }

    public static function paginated(bool $activeOnly, int $limit, int $offset): array
    {
        $sql = 'SELECT c.*, creator.name AS created_by_name, updater.name AS updated_by_name
                FROM companies c
                LEFT JOIN users creator ON creator.id = c.created_by
                LEFT JOIN users updater ON updater.id = c.updated_by';

        if ($activeOnly) {
            $sql .= ' WHERE c.is_active = 1';
        }

        $sql .= ' ORDER BY c.is_active DESC, c.name LIMIT :limit OFFSET :offset';
        $stmt = db()->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function countAll(bool $activeOnly = false): int
    {
        $sql = 'SELECT COUNT(*) FROM companies c';

        if ($activeOnly) {
            $sql .= ' WHERE c.is_active = 1';
        }

        return (int) db()->query($sql)->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT c.*, creator.name AS created_by_name, updater.name AS updated_by_name
             FROM companies c
             LEFT JOIN users creator ON creator.id = c.created_by
             LEFT JOIN users updater ON updater.id = c.updated_by
             WHERE c.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $company = $stmt->fetch();

        return $company ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO companies (name, tag_pattern, is_active, created_by, updated_by)
             VALUES (:name, :tag_pattern, :is_active, :created_by, :updated_by)'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $data['id'] = $id;
        $stmt = db()->prepare(
            'UPDATE companies SET
                name = :name,
                tag_pattern = :tag_pattern,
                is_active = :is_active,
                updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public static function deactivate(int $id, int $userId): void
    {
        $stmt = db()->prepare('UPDATE companies SET is_active = 0, updated_by = :updated_by WHERE id = :id');
        $stmt->execute(['id' => $id, 'updated_by' => $userId]);
    }

    public static function reactivate(int $id, int $userId): void
    {
        $stmt = db()->prepare('UPDATE companies SET is_active = 1, updated_by = :updated_by WHERE id = :id');
        $stmt->execute(['id' => $id, 'updated_by' => $userId]);
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM companies WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function duplicateNameExists(string $name, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT id FROM companies WHERE name = :name';
        $params = ['name' => $name];

        if ($ignoreId) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }

        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public static function tagCode(?array $company): string
    {
        if (!$company) {
            return 'EMP';
        }

        if (!empty($company['tag_pattern'])) {
            $patternClean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $company['tag_pattern']));
            if ($patternClean !== '') {
                return substr($patternClean, 0, 6);
            }
        }

        $name = trim((string) ($company['name'] ?? ''));
        if ($name === '') {
            return 'EMP';
        }

        $firstWord = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', explode(' ', $name)[0] ?? ''));
        if ($firstWord !== '') {
            return strlen($firstWord) <= 5 ? $firstWord : substr($firstWord, 0, 3);
        }

        $allClean = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $name));

        return $allClean !== '' ? substr($allClean, 0, 3) : 'EMP';
    }
}
