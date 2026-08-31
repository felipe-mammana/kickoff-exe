<?php

declare(strict_types=1);

class CompanyAttachment
{
    public static function byCompany(int $companyId): array
    {
        $stmt = db()->prepare(
            'SELECT a.*, u.name AS uploaded_by_name, cat.name AS category_name, cat.icon AS category_icon
             FROM company_attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             LEFT JOIN vault_categories cat ON cat.id = a.category_id
             WHERE a.company_id = :company_id
             ORDER BY a.created_at DESC, a.id DESC'
        );
        $stmt->execute(['company_id' => $companyId]);

        return $stmt->fetchAll();
    }

    public static function byCompanyAndCategory(int $companyId, ?int $categoryId): array
    {
        $sql =
            'SELECT a.*, u.name AS uploaded_by_name, cat.name AS category_name, cat.icon AS category_icon
             FROM company_attachments a
             LEFT JOIN users u ON u.id = a.uploaded_by
             LEFT JOIN vault_categories cat ON cat.id = a.category_id
             WHERE a.company_id = :company_id';
        $params = ['company_id' => $companyId];

        if ($categoryId !== null) {
            $sql .= ' AND a.category_id = :category_id';
            $params['category_id'] = $categoryId;
        } else {
            $sql .= ' AND a.category_id IS NULL';
        }

        $sql .= ' ORDER BY a.created_at DESC, a.id DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare(
            'SELECT a.*, c.name AS company_name, c.is_active AS company_is_active
             FROM company_attachments a
             INNER JOIN companies c ON c.id = a.company_id
             WHERE a.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $attachment = $stmt->fetch();

        return $attachment ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO company_attachments (
                company_id, category_id, disk_name, original_name, mime_type, file_size, description, uploaded_by
            ) VALUES (
                :company_id, :category_id, :disk_name, :original_name, :mime_type, :file_size, :description, :uploaded_by
            )'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM company_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
