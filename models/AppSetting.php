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

    public static function getJson(string $key, array $default = []): array
    {
        $value = self::get($key, '');
        if ($value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function setJson(string $key, array $value): void
    {
        self::set($key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function auditRetentionDays(): int
    {
        $days = (int) self::get('audit_retention_days', '365');

        return in_array($days, [30, 60, 90, 180, 365, 730, 1095], true) ? $days : 365;
    }

    public static function defaultDeviceSettings(): array
    {
        return [
            'label_prefixes' => [
                'notebook' => 'N{empresa}',
                'cpu' => 'C{empresa}',
                'roteador' => 'R{empresa}',
                'access_point' => 'AP{empresa}',
                'modem' => 'LINK',
                'impressora' => 'I{empresa}',
                'outros' => '',
            ],
            'default_categories' => array_keys(Machine::deviceTypes()),
            'required_fields' => self::defaultDeviceRequiredFields(),
            'photo_max_mb' => self::bytesToMb(MAX_UPLOAD_BYTES),
            'photo_mimes' => ALLOWED_IMAGE_MIMES,
            'attachment_max_mb' => self::bytesToMb(COMPANY_ATTACHMENT_MAX_BYTES),
            'attachment_extensions' => self::defaultAttachmentExtensions(),
        ];
    }

    public static function deviceSettings(): array
    {
        $defaults = self::defaultDeviceSettings();
        $settings = array_replace_recursive($defaults, self::getJson('device_settings', []));

        $settings['label_prefixes'] = array_intersect_key((array) $settings['label_prefixes'], Machine::deviceTypes()) + $defaults['label_prefixes'];
        $settings['default_categories'] = array_values(array_intersect((array) $settings['default_categories'], array_keys(Machine::deviceTypes())));
        if (!$settings['default_categories']) {
            $settings['default_categories'] = $defaults['default_categories'];
        }

        $settings['required_fields'] = self::normalizeRequiredFields((array) $settings['required_fields']);
        $settings['photo_max_mb'] = self::normalizeUploadMb($settings['photo_max_mb']);
        $settings['attachment_max_mb'] = self::normalizeUploadMb($settings['attachment_max_mb']);
        $settings['photo_mimes'] = array_values(array_intersect((array) $settings['photo_mimes'], ALLOWED_IMAGE_MIMES));
        if (!$settings['photo_mimes']) {
            $settings['photo_mimes'] = $defaults['photo_mimes'];
        }

        $settings['attachment_extensions'] = array_values(array_intersect((array) $settings['attachment_extensions'], self::defaultAttachmentExtensions()));
        if (!$settings['attachment_extensions']) {
            $settings['attachment_extensions'] = $defaults['attachment_extensions'];
        }

        return $settings;
    }

    public static function defaultDeviceRequiredFields(): array
    {
        return [
            'notebook' => ['tag', 'old_hostname', 'new_hostname', 'employee_name', 'department', 'machine_password'],
            'cpu' => ['tag', 'old_hostname', 'new_hostname', 'employee_name', 'department', 'machine_password'],
            'roteador' => ['tag', 'admin_user', 'admin_password', 'ip_address'],
            'access_point' => ['install_location', 'tag'],
            'modem' => ['tag', 'admin_user', 'admin_password', 'carrier'],
            'impressora' => ['tag', 'brand', 'printer_connection_type'],
            'outros' => ['tag'],
        ];
    }

    public static function defaultAttachmentExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'zip'];
    }

    public static function defaultVaultSettings(): array
    {
        return [
            'default_categories' => [
                ['name' => 'Acessos administrativos', 'icon' => 'shield', 'fields' => [], 'children' => []],
                ['name' => 'Bancos de dados', 'icon' => 'database', 'fields' => [], 'children' => []],
                ['name' => 'Cloud e hospedagem', 'icon' => 'cloud', 'fields' => [], 'children' => []],
                ['name' => 'E-mails e comunicação', 'icon' => 'mail', 'fields' => [], 'children' => []],
                ['name' => 'Financeiro', 'icon' => 'credit-card', 'fields' => [], 'children' => []],
                [
                    'name' => 'Rede e infraestrutura',
                    'icon' => 'router',
                    'fields' => [],
                    'children' => [
                        [
                            'name' => 'Roteadores',
                            'icon' => 'router',
                            'fields' => [
                                ['label' => 'IP de acesso', 'key' => 'ip_de_acesso', 'type' => 'text', 'required' => false],
                                ['label' => 'LAN', 'key' => 'lan', 'type' => 'text', 'required' => false],
                                ['label' => 'Nome do Wi-Fi', 'key' => 'nome_do_wifi', 'type' => 'text', 'required' => false],
                            ],
                        ],
                        [
                            'name' => 'Wi-Fi',
                            'icon' => 'wifi',
                            'fields' => [
                                ['label' => 'SSID', 'key' => 'ssid', 'type' => 'text', 'required' => false],
                                ['label' => 'IP de acesso', 'key' => 'ip_de_acesso', 'type' => 'text', 'required' => false],
                            ],
                        ],
                    ],
                ],
                ['name' => 'Sistemas internos', 'icon' => 'settings', 'fields' => [], 'children' => []],
                ['name' => 'Outros', 'icon' => 'folder', 'fields' => [], 'children' => []],
            ],
            'allow_password_copy' => true,
            'require_reveal_confirmation' => false,
            'audit_secret_access' => true,
            'credential_expiration_days' => 365,
        ];
    }

    public static function vaultSettings(): array
    {
        $settings = array_replace(self::defaultVaultSettings(), self::getJson('vault_settings', []));
        $settings['default_categories'] = self::normalizeVaultDefaultCategories((array) $settings['default_categories']);
        if (!$settings['default_categories']) {
            $settings['default_categories'] = self::defaultVaultSettings()['default_categories'];
        }

        $settings['allow_password_copy'] = !empty($settings['allow_password_copy']);
        $settings['require_reveal_confirmation'] = !empty($settings['require_reveal_confirmation']);
        $settings['audit_secret_access'] = !empty($settings['audit_secret_access']);
        $days = (int) ($settings['credential_expiration_days'] ?? 365);
        $settings['credential_expiration_days'] = in_array($days, [0, 30, 60, 90, 180, 365, 730, 1095], true) ? $days : 365;

        return $settings;
    }

    public static function normalizeVaultDefaultCategories(array $categories): array
    {
        $items = [];
        $icons = VaultCategory::iconOptions();

        foreach ($categories as $category) {
            if (is_string($category)) {
                $category = ['name' => $category, 'icon' => 'folder', 'fields' => [], 'children' => []];
            }
            if (!is_array($category)) {
                continue;
            }

            $name = trim((string) ($category['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $name = self::limitText($name, 120);
            $icon = (string) ($category['icon'] ?? 'folder');
            if (!array_key_exists($icon, $icons)) {
                $icon = 'folder';
            }

            $children = [];
            foreach ((array) ($category['children'] ?? []) as $child) {
                if (is_string($child)) {
                    $child = ['name' => $child, 'icon' => 'folder', 'fields' => []];
                }
                if (!is_array($child)) {
                    continue;
                }

                $childName = trim((string) ($child['name'] ?? ''));
                if ($childName === '') {
                    continue;
                }

                $childIcon = (string) ($child['icon'] ?? 'folder');
                if (!array_key_exists($childIcon, $icons)) {
                    $childIcon = 'folder';
                }

                $children[] = [
                    'name' => self::limitText($childName, 120),
                    'icon' => $childIcon,
                    'fields' => self::normalizeVaultCustomFields((array) ($child['fields'] ?? [])),
                ];
                if (count($children) >= 20) {
                    break;
                }
            }

            $items[] = [
                'name' => $name,
                'icon' => $icon,
                'fields' => self::normalizeVaultCustomFields((array) ($category['fields'] ?? [])),
                'children' => $children,
            ];
            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    public static function vaultCustomFieldTypeLabels(): array
    {
        return [
            'text' => 'Texto curto',
            'textarea' => 'Texto longo',
            'url' => 'URL',
            'email' => 'E-mail',
            'number' => 'Número',
        ];
    }

    public static function normalizeVaultCustomFields(array $fields): array
    {
        $items = [];
        $types = array_keys(self::vaultCustomFieldTypeLabels());
        $usedKeys = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = trim((string) ($field['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            $key = $key !== '' ? self::customFieldKey($key) : self::customFieldKey($label);
            if ($key === '') {
                $key = 'campo';
            }

            $baseKey = $key;
            $suffix = 2;
            while (isset($usedKeys[$key])) {
                $key = substr($baseKey, 0, 52) . '_' . $suffix;
                $suffix++;
            }
            $usedKeys[$key] = true;

            $type = (string) ($field['type'] ?? 'text');
            if (!in_array($type, $types, true)) {
                $type = 'text';
            }

            $items[] = [
                'label' => self::limitText($label, 80),
                'key' => self::limitText($key, 60),
                'type' => $type,
                'required' => !empty($field['required']),
            ];
            if (count($items) >= 20) {
                break;
            }
        }

        return $items;
    }

    public static function vaultAllowsPasswordCopy(): bool
    {
        return !empty(self::vaultSettings()['allow_password_copy']);
    }

    public static function vaultRequiresRevealConfirmation(): bool
    {
        return !empty(self::vaultSettings()['require_reveal_confirmation']);
    }

    public static function vaultAuditsSecretAccess(): bool
    {
        return true;
    }

    public static function deviceFieldLabels(): array
    {
        return [
            'tag' => 'Etiqueta',
            'old_hostname' => 'Hostname antigo',
            'new_hostname' => 'Hostname novo',
            'employee_name' => 'Colaborador',
            'department' => 'Departamento',
            'brand' => 'Marca',
            'computer_model' => 'Modelo',
            'operating_system' => 'Sistema operacional',
            'machine_password' => 'Senha do equipamento',
            'admin_user' => 'Usuário administrador',
            'admin_password' => 'Senha administrador',
            'install_location' => 'Local de instalação',
            'modem_name' => 'Nome do modem',
            'ip_address' => 'IP de acesso',
            'gateway' => 'Gateway',
            'carrier' => 'Operadora',
            'printer_brand' => 'Marca da impressora',
            'printer_connection_type' => 'Tipo de conexão',
            'notes' => 'Observações',
        ];
    }

    public static function normalizeTextList(array $values, int $limit, int $maxLength): array
    {
        $items = [];
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            $text = function_exists('mb_substr') ? mb_substr($text, 0, $maxLength) : substr($text, 0, $maxLength);
            $key = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
            if (!isset($items[$key])) {
                $items[$key] = $text;
            }
            if (count($items) >= $limit) {
                break;
            }
        }

        return array_values($items);
    }

    private static function limitText(string $text, int $maxLength): string
    {
        return function_exists('mb_substr') ? mb_substr($text, 0, $maxLength) : substr($text, 0, $maxLength);
    }

    private static function normalizeRequiredFields(array $fieldsByType): array
    {
        $allowed = array_keys(self::deviceFieldLabels());
        $defaults = self::defaultDeviceRequiredFields();
        $normalized = [];

        foreach (Machine::deviceTypes() as $type => $_label) {
            $fields = (array) ($fieldsByType[$type] ?? $defaults[$type] ?? []);
            $fields = array_values(array_intersect($fields, $allowed));
            $normalized[$type] = $fields ?: ($defaults[$type] ?? ['tag']);
        }

        return $normalized;
    }

    private static function normalizeUploadMb($value): int
    {
        return min(25, max(1, (int) $value));
    }

    private static function customFieldKey(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $key = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '_', $ascii), '_'));

        return substr($key, 0, 60);
    }

    private static function bytesToMb(int $bytes): int
    {
        return max(1, (int) ceil($bytes / 1024 / 1024));
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
