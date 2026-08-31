<?php

declare(strict_types=1);

class MachinePhoto
{
    public const TOPICS = [
        'local' => 'Local',
        'ambiente' => 'Ambiente',
        'equipamento' => 'Equipamento',
        'outras' => 'Outras',
    ];

    public static function topics(): array
    {
        return self::TOPICS;
    }

    public static function topicLabel(?string $topic): string
    {
        return self::TOPICS[$topic ?? ''] ?? self::TOPICS['equipamento'];
    }

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
        $topic = (string) ($data['photo_topic'] ?? 'equipamento');
        $data['photo_topic'] = array_key_exists($topic, self::TOPICS) ? $topic : 'equipamento';
        $data['location_name'] = trim((string) ($data['location_name'] ?? '')) ?: null;

        $stmt = db()->prepare(
            'INSERT INTO machine_photos (machine_id, photo_type, photo_topic, location_name, file_name, original_name, mime_type, file_size)
             VALUES (:machine_id, :photo_type, :photo_topic, :location_name, :file_name, :original_name, :mime_type, :file_size)'
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

    public static function findByFileName(string $fileName): ?array
    {
        $stmt = db()->prepare('SELECT * FROM machine_photos WHERE file_name = :file_name LIMIT 1');
        $stmt->execute(['file_name' => $fileName]);
        $photo = $stmt->fetch();

        return $photo ?: null;
    }

    public static function delete(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM machine_photos WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
