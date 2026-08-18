<?php

declare(strict_types=1);

class MachinePhoto
{
    public static function byMachine(int $machineId): array
    {
        $stmt = db()->prepare('SELECT * FROM machine_photos WHERE machine_id = :machine_id ORDER BY created_at DESC');
        $stmt->execute(['machine_id' => $machineId]);

        return $stmt->fetchAll();
    }

    public static function groupedByMachines(array $machineIds): array
    {
        $machineIds = array_values(array_filter(array_map('intval', $machineIds)));

        if (!$machineIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($machineIds), '?'));
        $stmt = db()->prepare(
            "SELECT *
             FROM machine_photos
             WHERE machine_id IN ({$placeholders})
             ORDER BY created_at DESC"
        );
        $stmt->execute($machineIds);

        $grouped = [];
        foreach ($stmt->fetchAll() as $photo) {
            $grouped[(int) $photo['machine_id']][] = $photo;
        }

        return $grouped;
    }


    public static function create(array $data): int
    {
        $stmt = db()->prepare(
            'INSERT INTO machine_photos (machine_id, photo_type, file_name, original_name, mime_type, file_size)
             VALUES (:machine_id, :photo_type, :file_name, :original_name, :mime_type, :file_size)'
        );
        $stmt->execute($data);

        return (int) db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM machine_photos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $photo = $stmt->fetch();

        return $photo ?: null;
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM machine_photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
