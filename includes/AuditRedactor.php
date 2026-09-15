<?php
declare(strict_types=1);

class AuditRedactor
{
    public static function clean($value)
    {
        if (!is_array($value)) return $value;
        foreach ($value as $key => $item) {
            $name = mb_strtolower((string) $key, 'UTF-8');
            if (preg_match('/password|senha|secret|token|(^|_)code($|_)|codigo|código|notes|observa|custom_fields|campos personalizados/', $name)) {
                $value[$key] = '[protegido]';
            } else {
                $value[$key] = self::clean($item);
            }
        }
        return $value;
    }

    public static function historical(?string $json): ?string
    {
        if ($json === null || $json === '') return $json;
        try {
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            // Free-form legacy payloads cannot be classified safely.
            if (!is_array($value)) return json_encode('[protegido]');
            return json_encode(self::clean($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return json_encode('[protegido]');
        }
    }
}
