<?php
declare(strict_types=1);

final class CryptoRotation
{
    private const TARGETS = [
        ['vault_credentials', 'id', 'secret_value', 'credentials'],
        ['machines', 'id', 'machine_password', 'credentials'],
        ['machines', 'id', 'admin_password', 'credentials'],
        ['users', 'id', 'two_factor_secret', 'totp'],
        ['app_settings', 'setting_key', 'setting_value', 'microsoft'],
    ];

    public static function run(PDO $pdo, bool $apply = false, bool $discardInvalidTestCredentials = false): array
    {
        if ($pdo->inTransaction()) throw new RuntimeException('Transacao existente.');
        // Configuration failures must never be mistaken for disposable records.
        foreach (['credentials', 'totp', 'microsoft'] as $purpose) {
            $probe = bin2hex(random_bytes(16));
            $encrypted = CredentialCrypto::encrypt($probe, $purpose);
            if (!str_starts_with((string) $encrypted, 'enc:v2:')
                || CredentialCrypto::decrypt($encrypted, $purpose) !== $probe) {
                throw new RuntimeException('Chave ativa invalida para ' . $purpose);
            }
        }
        $counts = [];
        $pdo->beginTransaction();
        try {
            foreach (self::TARGETS as [$table, $pk, $column, $purpose]) {
                $where = $table === 'app_settings' ? " AND setting_key = 'microsoft_mail_tokens'" : '';
                $query = "SELECT `$pk`, `$column` FROM `$table` WHERE `$column` IS NOT NULL AND `$column` <> ''" . $where;
                // Apply mode locks the source rows until the entire migration commits.
                $rows = $pdo->query($query . ($apply ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
                $counts[$table . '.' . $column] = 0;
                foreach ($rows as $row) {
                    $old = (string) $row[$column];
                    try {
                        $new = CredentialCrypto::rotate($old, $purpose);
                    } catch (RuntimeException $e) {
                        if (!$discardInvalidTestCredentials || $purpose !== 'credentials') {
                            throw new RuntimeException('Falha em ' . $table . '.' . $column . ' registro ' . $row[$pk] . ': ' . $e->getMessage());
                        }
                        $new = '';
                        $discardKey = 'cleared:' . $table . '.' . $column;
                        $counts[$discardKey] = ($counts[$discardKey] ?? 0) + 1;
                    }
                    if ($apply) {
                        $stmt = $pdo->prepare("UPDATE `$table` SET `$column` = :value WHERE `$pk` = :id");
                        $stmt->execute(['value' => $new, 'id' => $row[$pk]]);
                    }
                    $counts[$table . '.' . $column]++;
                }
            }
            if ($apply) $pdo->commit(); else $pdo->rollBack();
            return $counts;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
