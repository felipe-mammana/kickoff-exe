<?php
declare(strict_types=1);
if (!defined('APP_ENV') || APP_ENV !== 'testing') exit(1);
$presenceUser = User::create(['name' => 'Presence Fixture', 'email' => 'presence@example.test', 'password' => 'Presence-Test-123', 'role' => 'viewer', 'is_active' => 1]);
$presenceToken = bin2hex(random_bytes(32));
User::setActiveSession($presenceUser, $presenceToken);
SessionPresence::touch($presenceUser, $presenceToken);
$presenceRow = static function () use ($presenceUser): array {
    foreach (SessionPresence::all() as $row) if ((int) $row['id'] === $presenceUser) return $row;
    throw new RuntimeException('Missing presence fixture');
};
check('Sessoes: atividade recente fica online', (int) $presenceRow()['online'] === 1);
check('Sessoes: listagem nao expoe tokens ou hashes', !str_contains(serialize($presenceRow()), $presenceToken) && !isset($presenceRow()['session_hash']));
db()->prepare('UPDATE session_presence SET last_seen_at = DATE_SUB(NOW(), INTERVAL 121 SECOND) WHERE user_id = ?')->execute([$presenceUser]);
check('Sessoes: inatividade fica offline', (int) $presenceRow()['online'] === 0);
SessionPresence::touch($presenceUser, $presenceToken);
User::clearActiveSession($presenceUser);
check('Sessoes: logout fica offline sem perder ultimo IP', (int) $presenceRow()['online'] === 0 && $presenceRow()['ip_address'] !== null);
check('Sessoes: navegador Edge identificado antes de Chrome', SessionPresence::browser('Chrome/120.0 Edg/120.0') === 'Edge 120');
