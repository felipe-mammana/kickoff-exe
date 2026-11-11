<?php
$records = is_array($records ?? null) ? $records : null;
$files = is_array($files ?? null) ? $files : [];
$formatBytes = static function (int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
};
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a><span><?= icon('chevron-right') ?></span>
    <a href="/?route=audit.index">Auditoria</a><span><?= icon('chevron-right') ?></span>
    <strong>Arquivos protegidos</strong>
</nav>

<section class="asset-page-head">
    <div>
        <span class="eyebrow">Auditoria protegida</span>
        <h1>Arquivos de auditoria</h1>
        <p>Consulte registros criptografados por data UTC. A leitura exige confirmação da senha atual e fica registrada.</p>
    </div>
    <div class="header-actions"><a class="btn btn-muted" href="/?route=audit.index"><?= icon('file-clock') ?><span>Logs do sistema</span></a></div>
</section>

<div class="audit-file-layout">
    <section class="asset-panel audit-file-query">
        <header class="asset-panel-head"><div><span><?= icon('unlock') ?></span><div><h2>Consultar arquivo</h2><p>Informe a data e confirme sua identidade.</p></div></div></header>
        <form class="company-form" method="post" action="/?route=settings.auditFiles" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Data do arquivo (UTC)</span><input type="date" name="date" value="<?= e($date) ?>" required></label>
            <label class="field"><span>Senha atual</span><input type="password" name="password" autocomplete="current-password" required></label>
            <?php if ($error !== ''): ?><p class="audit-inline-message danger" role="alert"><?= e($error) ?></p><?php endif; ?>
            <?php if ($notice !== ''): ?><p class="audit-inline-message" role="status"><?= e($notice) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('unlock') ?><span>Consultar com segurança</span></button></div>
        </form>
    </section>

    <section class="asset-panel audit-file-list-panel">
        <header class="asset-panel-head"><div><span><?= icon('history') ?></span><div><h2>Arquivos disponíveis</h2><p>Somente arquivos válidos do diretório protegido são listados.</p></div></div></header>
        <?php if ($filesError !== ''): ?>
            <p class="audit-inline-message danger" role="alert"><?= e($filesError) ?></p>
        <?php elseif (!$files): ?>
            <div class="empty-state compact"><h3>Nenhum arquivo encontrado</h3><p>A auditoria protegida ainda não possui arquivos disponíveis.</p></div>
        <?php else: ?>
            <div class="inventory-table-wrap"><table class="inventory-table audit-file-table">
                <thead><tr><th>Data UTC</th><th>Tamanho</th><th>Atualizado</th><th></th></tr></thead>
                <tbody><?php foreach ($files as $file): ?><tr>
                    <td data-label="Data UTC"><strong><?= e((string) $file['date']) ?></strong></td>
                    <td data-label="Tamanho"><?= e($formatBytes((int) $file['size'])) ?></td>
                    <td data-label="Atualizado"><?= e((string) $file['modified_at']) ?></td>
                    <td><a class="btn btn-muted compact-btn" href="/?route=settings.auditFiles&amp;date=<?= e(rawurlencode((string) $file['date'])) ?>">Selecionar</a></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>

<?php if ($records !== null && $records): ?>
<section class="asset-panel audit-file-records">
    <header class="asset-panel-head"><div><span><?= icon('file-clock') ?></span><div><h2>Registros de <?= e($date) ?></h2><p><?= count($records) ?> linha(s) processada(s). Linhas ilegíveis são isoladas sem interromper as demais.</p></div></div></header>
    <div class="audit-protected-record-list">
        <?php foreach ($records as $record): ?>
            <?php if (($record['status'] ?? '') !== 'ok'): ?>
                <article class="audit-protected-record is-error"><header><strong>Linha <?= (int) ($record['line'] ?? 0) ?></strong><span class="status-chip danger">Registro indisponível</span></header><p>Esta linha não pôde ser autenticada ou decodificada. Os demais registros continuam disponíveis.</p></article>
                <?php continue; ?>
            <?php endif; ?>
            <?php
            $data = is_array($record['data'] ?? null) ? $record['data'] : [];
            $event = is_array($data['event'] ?? null) ? $data['event'] : [];
            $action = trim((string) ($event['action_type'] ?? 'Evento de auditoria'));
            $description = trim((string) ($event['description'] ?? 'Sem descrição.'));
            $recordedAt = trim((string) ($data['recorded_at'] ?? 'Data não informada'));
            ?>
            <article class="audit-protected-record">
                <header><div><strong><?= e($action !== '' ? $action : 'Evento de auditoria') ?></strong><small>Linha <?= (int) ($record['line'] ?? 0) ?> · <?= e($recordedAt) ?></small></div><span class="status-chip success">Íntegro</span></header>
                <p><?= e($description !== '' ? $description : 'Sem descrição.') ?></p>
                <dl class="audit-card-meta">
                    <div><dt>Usuário</dt><dd><?= e((string) ($event['user_name'] ?? 'Não informado')) ?></dd></div>
                    <div><dt>E-mail</dt><dd><?= e((string) ($event['user_email'] ?? 'Não informado')) ?></dd></div>
                    <div><dt>IP</dt><dd><?= e((string) ($event['ip_address'] ?? 'Não informado')) ?></dd></div>
                    <div><dt>Registro</dt><dd><?= e((string) ($event['affected_record_id'] ?? 'Não informado')) ?></dd></div>
                </dl>
                <details><summary>Ver dados protegidos do evento</summary><pre><?= e((string) json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
