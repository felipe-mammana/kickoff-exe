<?php

declare(strict_types=1);

class AppSetting
{
    public static function get(string $key, string $default = ''): string
    {
        self::ensureTable();

        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute(['setting_key' => $key]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : $default;
    }

    public static function set(string $key, string $value): void
    {
        self::ensureTable();

        $stmt = db()->prepare(
            'INSERT INTO app_settings (setting_key, setting_value, updated_by)
             VALUES (:setting_key, :setting_value, :updated_by)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'setting_key' => $key,
            'setting_value' => $value,
            'updated_by' => current_user()['id'] ?? null,
        ]);
    }

    public static function auditRetentionDays(): int
    {
        $days = (int) self::get('audit_retention_days', '365');

        return in_array($days, [30, 60, 90, 180, 365, 730, 1095], true) ? $days : 365;
    }

    private static function ensureTable(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS app_settings (
                setting_key VARCHAR(120) NOT NULL PRIMARY KEY,
                setting_value TEXT NOT NULL,
                updated_by INT UNSIGNED NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_app_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB'
        );

        $ensured = true;
    }
}
