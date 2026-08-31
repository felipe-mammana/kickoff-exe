<?php
$actionLabels = [
    'company_created' => 'Empresa cadastrada',
    'company_updated' => 'Empresa editada',
    'company_deactivated' => 'Empresa desativada',
    'company_reactivated' => 'Empresa reativada',
    'company_deleted' => 'Empresa excluída',
    'company_attachment_added' => 'Anexo adicionado',
    'company_attachment_downloaded' => 'Anexo baixado',
    'company_attachment_deleted' => 'Anexo removido',
    'machine_created' => 'Dispositivo cadastrado',
    'machine_updated' => 'Dispositivo editado',
    'machine_deactivated' => 'Dispositivo desativado',
    'machine_photos_added' => 'Fotos adicionadas',
    'machine_photo_removed' => 'Foto removida',
    'credential_viewed' => 'Credencial visualizada',
    'vault_credential_created' => 'Credencial cadastrada',
    'vault_credential_updated' => 'Credencial editada',
    'vault_credential_deactivated' => 'Credencial desativada',
    'vault_credential_revealed' => 'Credencial revelada',
    'vault_credential_reveal_password_failed' => 'Falha ao revelar credencial',
    'vault_category_created' => 'Categoria cadastrada',
    'login_success' => 'Login aprovado',
    'login_failed' => 'Falha de login',
    'login_2fa_failed' => 'Falha no 2FA',
    'login_2fa_email_sent' => 'Código 2FA enviado',
    'login_inactive_user' => 'Login bloqueado',
    'logout' => 'Logout',
    'api_token_created' => 'Token de API criado',
    'api_token_revoked' => 'Token de API revogado',
    'export_performed' => 'Exportação realizada',
    'user_created' => 'Usuário cadastrado',
    'user_updated' => 'Usuário editado',
    'user_profile_updated' => 'Perfil atualizado',
    'user_password_changed' => 'Senha alterada',
    'user_preferences_updated' => 'Preferências atualizadas',
    'user_security_preferences_updated' => 'Segurança atualizada',
    'user_sessions_revoked' => 'Sessões encerradas',
    'user_password_reset' => 'Senha redefinida',
    'user_2fa_enabled' => '2FA ativado',
    'user_2fa_disabled' => '2FA desativado',
    'user_2fa_email_test_sent' => 'Teste de e-mail 2FA',
    'user_activated' => 'Usuário ativado',
    'user_deactivated' => 'Usuário desativado',
];

$actionClasses = [
    'login_failed' => 'danger',
    'login_2fa_failed' => 'danger',
    'login_2fa_email_sent' => 'info',
    'login_inactive_user' => 'danger',
    'machine_deactivated' => 'danger',
    'company_deactivated' => 'danger',
    'company_deleted' => 'danger',
    'company_attachment_deleted' => 'danger',
    'vault_credential_deactivated' => 'danger',
    'vault_credential_reveal_password_failed' => 'danger',
    'user_deactivated' => 'danger',
    'login_success' => 'success',
    'company_created' => 'success',
    'company_reactivated' => 'success',
    'company_attachment_added' => 'success',
    'machine_created' => 'success',
    'vault_credential_created' => 'success',
    'vault_category_created' => 'success',
    'api_token_created' => 'success',
    'user_created' => 'success',
    'user_activated' => 'success',
    'company_updated' => 'info',
    'machine_updated' => 'info',
    'vault_credential_updated' => 'info',
    'user_updated' => 'info',
    'user_profile_updated' => 'info',
    'user_preferences_updated' => 'info',
    'user_security_preferences_updated' => 'info',
    'user_sessions_revoked' => 'info',
    'user_password_changed' => 'warning',
    'user_password_reset' => 'warning',
    'user_2fa_enabled' => 'success',
    'user_2fa_disabled' => 'warning',
    'user_2fa_email_test_sent' => 'info',
    'machine_photos_added' => 'info',
    'machine_photo_removed' => 'warning',
    'company_attachment_downloaded' => 'warning',
    'credential_viewed' => 'warning',
    'vault_credential_revealed' => 'warning',
    'api_token_revoked' => 'warning',
    'export_performed' => 'info',
    'logout' => 'muted',
];

$shortText = static function ($value, int $maxLength = 80): string {
    $value = trim((string) $value);
    if ($value === '') {
        return 'Não informado';
    }

    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if ($length <= $maxLength) {
        return $value;
    }

    return (function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength)) . '...';
};

$activeFilters = 0;
foreach ($filters as $filterValue) {
    if ((string) $filterValue !== '') {
        $activeFilters++;
    }
}
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <strong>Logs do sistema</strong>
</nav>

<section class="asset-page-head">
    <div>
        <span class="eyebrow">Auditoria</span>
        <h1>Logs do sistema</h1>
        <p>Histórico centralizado de acessos, cadastros, edições e remoções feitas no sistema EXE.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-muted" href="/?route=audit.index"><?= icon('history') ?><span>Atualizar</span></a>
        <a class="btn btn-primary" href="/"><?= icon('layout-dashboard') ?><span>Dashboard</span></a>
    </div>
</section>

<section class="audit-summary-grid">
    <article class="summary-card">
        <span class="summary-icon"><?= icon('file-clock') ?></span>
        <div>
            <strong><?= count($logs) ?></strong>
            <span>registros exibidos</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('filter') ?></span>
        <div>
            <strong><?= $activeFilters ?></strong>
            <span>filtros ativos</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('users') ?></span>
        <div>
            <strong><?= count($users) ?></strong>
            <span>usuários monitorados</span>
        </div>
    </article>
</section>

<section class="asset-panel audit-filter-panel">
    <header class="asset-panel-head">
        <div>
            <span><?= icon('filter') ?></span>
            <div>
                <h2>Filtros de auditoria</h2>
                <p>Refine por usuário, empresa, tipo de ação ou período.</p>
            </div>
        </div>
        <?php if ($activeFilters > 0): ?>
            <a class="link-primary" href="/?route=audit.index">Limpar filtros</a>
        <?php endif; ?>
    </header>

    <form class="audit-filter-grid" method="get" action="/">
        <input type="hidden" name="route" value="audit.index">

        <label class="field">
            <span>Usuário</span>
            <select name="user_id">
                <option value="">Todos os usuários</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int) $user['id'] ?>" <?= (string) $user['id'] === (string) $filters['user_id'] ? 'selected' : '' ?>>
                        <?= e($user['name']) ?> - <?= e($user['email']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Empresa</span>
            <select name="company_id">
                <option value="">Todas as empresas</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= (int) $company['id'] ?>" <?= (string) $company['id'] === (string) $filters['company_id'] ? 'selected' : '' ?>>
                        <?= e($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Tipo de ação</span>
            <select name="action_type">
                <option value="">Todas as ações</option>
                <?php foreach ($actionTypes as $actionType): ?>
                    <option value="<?= e($actionType) ?>" <?= $actionType === $filters['action_type'] ? 'selected' : '' ?>>
                        <?= e($actionLabels[$actionType] ?? $actionType) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="field">
            <span>Data inicial</span>
            <input type="date" name="date_from" value="<?= e($filters['date_from']) ?>">
        </label>

        <label class="field">
            <span>Data final</span>
            <input type="date" name="date_to" value="<?= e($filters['date_to']) ?>">
        </label>

        <div class="filter-actions audit-filter-actions">
            <button class="btn btn-primary" type="submit"><?= icon('search') ?><span>Aplicar filtros</span></button>
            <a class="btn btn-muted" href="/?route=audit.index"><?= icon('history') ?><span>Limpar</span></a>
        </div>
    </form>
</section>

<section class="asset-panel audit-log-panel">
    <header class="asset-panel-head">
        <div>
            <span><?= icon('file-clock') ?></span>
            <div>
                <h2>Registros recentes</h2>
                <p>Ordem cronológica conforme retorno da auditoria.</p>
            </div>
        </div>
        <div class="export-actions" data-export-actions>
            <span class="status-chip neutral"><?= count($logs) ?> exibidos</span>
            <a class="btn btn-muted export-btn <?= !$logs ? 'disabled' : '' ?>" href="<?= e(export_url('audit', 'csv', $filters)) ?>" data-export-link data-export-format="CSV" aria-disabled="<?= !$logs ? 'true' : 'false' ?>">
                <?= icon('file-spreadsheet') ?><span>Exportar CSV</span>
            </a>
            <a class="btn btn-muted export-btn <?= !$logs ? 'disabled' : '' ?>" href="<?= e(export_url('audit', 'json', $filters)) ?>" data-export-link data-export-format="JSON" aria-disabled="<?= !$logs ? 'true' : 'false' ?>">
                <?= icon('braces') ?><span>Exportar JSON</span>
            </a>
        </div>
    </header>

    <?php if (!$logs): ?>
        <div class="empty-state compact audit-empty">
            <span class="empty-icon"><?= icon('file-clock') ?></span>
            <h3>Nenhum log encontrado</h3>
            <p>Ajuste os filtros ou execute novas ações no sistema para alimentar a auditoria.</p>
        </div>
    <?php else: ?>
        <div class="audit-card-list">
            <?php foreach ($logs as $log): ?>
                <?php
                $actionType = (string) $log['action_type'];
                $badgeClass = $actionClasses[$actionType] ?? 'neutral';
                $userName = (string) ($log['user_name'] ?: 'Sem usuário');
                $userAgent = trim((string) ($log['user_agent'] ?? ''));
                $hasChanges = (bool) ($log['old_data'] || $log['new_data']);
                ?>
                <article class="audit-card">
                    <header>
                        <span class="audit-action-badge <?= e($badgeClass) ?>">
                            <?= icon($badgeClass === 'danger' ? 'warning' : 'check-circle') ?>
                            <?= e($actionLabels[$actionType] ?? $actionType) ?>
                        </span>
                        <time><?= e($log['created_at']) ?></time>
                    </header>

                    <strong><?= e($log['description']) ?></strong>

                    <dl class="audit-card-meta">
                        <div>
                            <dt>Usuário</dt>
                            <dd><?= e($userName) ?></dd>
                        </div>
                        <div>
                            <dt>Empresa</dt>
                            <dd><?= e($log['company_name'] ?: '-') ?></dd>
                        </div>
                        <div>
                            <dt>Dispositivo</dt>
                            <dd><?= e($log['machine_tag'] ?: '-') ?></dd>
                        </div>
                        <div>
                            <dt>IP</dt>
                            <dd><?= e($log['ip_address'] ?: '-') ?></dd>
                        </div>
                        <div>
                            <dt>Origem</dt>
                            <dd title="<?= e($userAgent) ?>"><?= e($userAgent !== '' ? $shortText($userAgent, 72) : 'User-agent não informado') ?></dd>
                        </div>
                    </dl>

                    <?php if ($hasChanges): ?>
                        <button
                            class="audit-change-trigger"
                            type="button"
                            data-audit-change-open
                            data-audit-title="<?= e($actionLabels[$actionType] ?? $actionType) ?>"
                            data-audit-description="<?= e($log['description']) ?>"
                            data-audit-before="<?= e(pretty_json($log['old_data'])) ?>"
                            data-audit-after="<?= e(pretty_json($log['new_data'])) ?>"
                        >
                            <?= icon('eye') ?><span>Ver alterações</span>
                        </button>
                    <?php else: ?>
                        <span class="status-chip neutral">Sem alterações</span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="inventory-table-wrap audit-table-wrap">
            <table class="inventory-table audit-table">
                <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Usuário</th>
                        <th>Origem</th>
                        <th>Registro relacionado</th>
                        <th>Dados</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $actionType = (string) $log['action_type'];
                        $badgeClass = $actionClasses[$actionType] ?? 'neutral';
                        $userName = (string) ($log['user_name'] ?: 'Sem usuário');
                        $initial = strtoupper(substr(trim($userName) !== '' ? trim($userName) : 'S', 0, 1));
                        $hasChanges = (bool) ($log['old_data'] || $log['new_data']);
                        $userAgent = trim((string) ($log['user_agent'] ?? ''));
                        ?>
                        <tr>
                            <td data-label="Evento">
                                <div class="audit-event-cell">
                                    <span class="audit-action-badge <?= e($badgeClass) ?>">
                                        <?= icon($badgeClass === 'danger' ? 'warning' : 'check-circle') ?>
                                        <?= e($actionLabels[$actionType] ?? $actionType) ?>
                                    </span>
                                    <strong><?= e($log['description']) ?></strong>
                                    <small><?= e($log['created_at']) ?></small>
                                </div>
                            </td>
                            <td data-label="Usuário">
                                <div class="audit-user-cell">
                                    <span class="audit-avatar"><?= e($initial) ?></span>
                                    <div>
                                        <strong><?= e($userName) ?></strong>
                                        <small><?= e($log['user_email'] ?: '-') ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Origem">
                                <div class="audit-muted-cell">
                                    <strong><?= e($log['ip_address'] ?: '-') ?></strong>
                                    <small title="<?= e($userAgent) ?>"><?= e($userAgent !== '' ? $shortText($userAgent) : 'User-agent não informado') ?></small>
                                </div>
                            </td>
                            <td data-label="Registro relacionado">
                                <dl class="audit-mini-meta">
                                    <div>
                                        <dt>Empresa</dt>
                                        <dd><?= e($log['company_name'] ?: '-') ?></dd>
                                    </div>
                                    <div>
                                        <dt>Dispositivo</dt>
                                        <dd><?= e($log['machine_tag'] ?: '-') ?></dd>
                                    </div>
                                </dl>
                            </td>
                            <td data-label="Dados">
                                <?php if ($hasChanges): ?>
                                    <button
                                        class="audit-change-trigger"
                                        type="button"
                                        data-audit-change-open
                                        data-audit-title="<?= e($actionLabels[$actionType] ?? $actionType) ?>"
                                        data-audit-description="<?= e($log['description']) ?>"
                                        data-audit-before="<?= e(pretty_json($log['old_data'])) ?>"
                                        data-audit-after="<?= e(pretty_json($log['new_data'])) ?>"
                                    >
                                        <?= icon('eye') ?><span>Ver alterações</span>
                                    </button>
                                <?php else: ?>
                                    <span class="status-chip neutral">Sem alterações</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="audit-change-modal" data-audit-change-modal hidden>
    <div class="audit-change-dialog" role="dialog" aria-modal="true" aria-labelledby="audit-change-title">
        <header class="audit-change-head">
            <div>
                <span class="eyebrow">Alterações registradas</span>
                <h2 id="audit-change-title" data-audit-change-title>Alterações</h2>
                <p data-audit-change-description></p>
            </div>
            <button class="icon-btn" type="button" data-audit-change-close aria-label="Fechar" title="Fechar"><?= icon('x') ?></button>
        </header>

        <div class="json-grid audit-json-grid audit-json-modal-grid">
            <div>
                <span>Antes</span>
                <pre data-audit-change-before>-</pre>
            </div>
            <div>
                <span>Depois</span>
                <pre data-audit-change-after>-</pre>
            </div>
        </div>
    </div>
</div>
