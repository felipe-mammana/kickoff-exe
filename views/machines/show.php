<?php
$type = (string) ($machine['device_type'] ?? 'notebook');
$typeLabel = Machine::typeLabel($type);
$value = static fn (string $field, string $default = ''): string => (string) (($machine[$field] ?? '') ?: $default);
$firstValue = static function (array $fields, string $default = '-') use ($machine): string {
    foreach ($fields as $field) {
        if (!empty($machine[$field])) {
            return (string) $machine[$field];
        }
    }

    return $default;
};
$photoSrc = static fn (array $photo): string => UPLOAD_URL . '/' . (string) ($photo['file_name'] ?? '');
$photoName = static fn (array $photo): string => (string) (($photo['original_name'] ?? '') ?: 'Foto do dispositivo');
$photoCaption = static function (array $photo) use ($photoName): string {
    $parts = [MachinePhoto::topicLabel($photo['photo_topic'] ?? 'equipamento')];
    if (($photo['photo_type'] ?? 'general') === 'network_config') {
        $parts[] = 'Rede';
    }
    $parts[] = $photoName($photo);

    return implode(' - ', $parts);
};
$title = $firstValue(['tag', 'new_hostname', 'old_hostname', 'modem_name', 'install_location', 'computer_model'], 'Dispositivo');
$networkPhotos = array_values(array_filter($photos, static fn (array $photo): bool => ($photo['photo_type'] ?? 'general') === 'network_config'));
$generalPhotos = array_values(array_filter($photos, static fn (array $photo): bool => ($photo['photo_type'] ?? 'general') !== 'network_config'));
$typeIcon = [
    'notebook' => 'laptop',
    'cpu' => 'monitor-cog',
    'roteador' => 'router',
    'access_point' => 'router',
    'modem' => 'router',
    'impressora' => 'printer',
][$type] ?? 'settings';

$details = [
    'Tipo' => $typeLabel,
    'Etiqueta' => $value('tag', '-'),
];

if (in_array($type, ['notebook', 'cpu'], true)) {
    $details += [
        'Hostname antigo' => $value('old_hostname', '-'),
        'Hostname novo' => $value('new_hostname', '-'),
        'Colaborador' => $value('employee_name', '-'),
        'Departamento' => $value('department', '-'),
        'Modelo' => $value('computer_model', '-'),
        'Senha da maquina' => $value('machine_password', '-'),
        'TFlux instalado' => !empty($machine['tflux_installed']) ? 'Sim' : 'Nao',
        'Antivirus instalado' => !empty($machine['antivirus_installed']) ? 'Sim' : 'Nao',
        'Solicitante cadastrado no TFlux' => !empty($machine['requester_in_tflux']) ? 'Sim' : 'Nao',
    ];
} elseif ($type === 'roteador') {
    $details += [
        'Modelo' => $value('computer_model', '-'),
        'Usuario administrador' => $value('admin_user', '-'),
        'Senha administrador' => $value('admin_password', '-'),
        'IP de acesso' => $value('ip_address', '-'),
    ];
} elseif ($type === 'access_point') {
    $details += [
        'Local de instalacao' => $value('install_location', '-'),
        'Modelo' => $value('computer_model', '-'),
    ];
} elseif ($type === 'modem') {
    $details += [
        'Operadora' => $value('carrier', '-'),
        'Modelo' => $value('computer_model', '-'),
        'Usuario administrador' => $value('admin_user', '-'),
        'Senha administrador' => $value('admin_password', '-'),
    ];
} elseif ($type === 'impressora') {
    $details += [
        'Marca' => $firstValue(['brand', 'printer_brand']),
        'Modelo' => $value('computer_model', '-'),
        'Tipo de conexao' => ($machine['printer_connection_type'] ?? '') === 'rede' ? 'Rede' : (($machine['printer_connection_type'] ?? '') === 'usb' ? 'USB' : '-'),
        'IP' => $value('ip_address', '-'),
        'Gateway' => $value('gateway', '-'),
        'Compartilhada' => !empty($machine['printer_shared']) ? 'Sim' : 'Nao',
    ];
} else {
    $details += [
        'Marca' => $firstValue(['brand', 'printer_brand']),
        'Modelo' => $value('computer_model', '-'),
        'Local / responsavel' => $value('install_location', '-'),
    ];
}
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?company_id=<?= (int) ($machine['company_id'] ?? 0) ?>">Dispositivos</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span><?= e($value('tag', '#' . (int) ($machine['id'] ?? 0))) ?></span>
</nav>

<section class="device-hero">
    <div>
        <div class="device-kicker">
            <span class="asset-type"><?= icon($typeIcon) ?><?= e($typeLabel) ?></span>
            <span class="status-badge <?= !empty($machine['is_active']) ? 'ok' : 'no' ?>"><?= !empty($machine['is_active']) ? 'Ativo' : 'Inativo' ?></span>
        </div>
        <h1><?= e($title) ?></h1>
        <p><?= e($value('company_name', 'Empresa')) ?> - <span class="mono"><?= e($value('tag', 'Sem etiqueta')) ?></span></p>
    </div>

    <div class="heading-actions">
        <a class="btn btn-muted" href="/?company_id=<?= (int) ($machine['company_id'] ?? 0) ?>"><?= icon('eye') ?><span>Voltar</span></a>
        <a class="btn btn-primary" href="/?route=machines.edit&id=<?= (int) ($machine['id'] ?? 0) ?>"><?= icon('edit-3') ?><span>Editar</span></a>
        <?php if (!empty($machine['is_active'])): ?>
            <form action="/?route=machines.deactivate&id=<?= (int) ($machine['id'] ?? 0) ?>" method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-danger" type="submit"><?= icon('trash-2') ?><span>Desativar</span></button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="device-summary-grid">
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon($typeIcon) ?></span>
        <div>
            <small>Tipo</small>
            <strong><?= e($typeLabel) ?></strong>
        </div>
    </article>
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon('building-2') ?></span>
        <div>
            <small>Empresa</small>
            <strong><?= e($value('company_name', '-')) ?></strong>
        </div>
    </article>
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon('tag') ?></span>
        <div>
            <small>Etiqueta</small>
            <strong class="mono"><?= e($value('tag', '-')) ?></strong>
        </div>
    </article>
</section>

<section class="device-detail-grid">
    <div class="device-main-column">
        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('file-text') ?>
                    <h2>Dados tecnicos</h2>
                </div>
                <span>Atualizado em <?= e($value('updated_at', '-')) ?></span>
            </header>

            <dl class="asset-property-grid">
                <?php foreach ($details as $label => $detailValue): ?>
                    <div>
                        <dt><?= e($label) ?></dt>
                        <dd class="<?= in_array($label, ['Etiqueta', 'IP', 'Gateway', 'IP de acesso'], true) ? 'mono' : '' ?>"><?= e((string) $detailValue) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <div class="asset-notes">
                <strong>Observacoes</strong>
                <p><?= nl2br(e($value('notes', 'Sem observacoes.'))) ?></p>
            </div>
        </article>

        <?php if ($networkPhotos): ?>
            <article class="asset-panel">
                <header class="asset-panel-head">
                    <div>
                        <?= icon('router') ?>
                        <h2>Configuracao de rede</h2>
                    </div>
                    <span><?= count($networkPhotos) ?> imagem(ns)</span>
                </header>
                <div class="asset-gallery">
                    <?php foreach ($networkPhotos as $photo): ?>
                        <figure>
                            <button class="photo-button" type="button" data-lightbox-src="<?= e($photoSrc($photo)) ?>" data-lightbox-alt="<?= e($photoName($photo)) ?>">
                                <img src="<?= e($photoSrc($photo)) ?>" alt="<?= e($photoName($photo)) ?>">
                            </button>
                            <figcaption><?= e($photoCaption($photo)) ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            </article>
        <?php endif; ?>

        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('camera') ?>
                    <h2>Galeria de fotos</h2>
                </div>
                <span><?= count($generalPhotos) ?> imagem(ns)</span>
            </header>

            <?php if (!$generalPhotos): ?>
                <div class="empty-state compact">
                    <h3>Nenhuma foto enviada</h3>
                    <p>Edite este cadastro para adicionar imagens do equipamento.</p>
                </div>
            <?php else: ?>
                <div class="asset-gallery">
                    <?php foreach ($generalPhotos as $photo): ?>
                        <figure>
                            <button class="photo-button" type="button" data-lightbox-src="<?= e($photoSrc($photo)) ?>" data-lightbox-alt="<?= e($photoName($photo)) ?>">
                                <img src="<?= e($photoSrc($photo)) ?>" alt="<?= e($photoName($photo)) ?>">
                            </button>
                            <figcaption><?= e($photoCaption($photo)) ?></figcaption>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </div>

    <aside class="device-side-column">
        <?php if (in_array($type, ['notebook', 'cpu'], true)): ?>
            <article class="asset-panel">
                <header class="asset-panel-head">
                    <div>
                        <?= icon('check-circle') ?>
                        <h2>Checklist de compliance</h2>
                    </div>
                </header>

                <div class="compliance-list">
                    <div class="<?= !empty($machine['tflux_installed']) ? 'ok' : '' ?>">
                        <?= icon(!empty($machine['tflux_installed']) ? 'check-circle' : 'circle') ?>
                        <span>TFlux instalado</span>
                        <small><?= !empty($machine['tflux_installed']) ? 'Verificado' : 'Pendente' ?></small>
                    </div>
                    <div class="<?= !empty($machine['antivirus_installed']) ? 'ok' : '' ?>">
                        <?= icon(!empty($machine['antivirus_installed']) ? 'check-circle' : 'circle') ?>
                        <span>Antivirus ativo</span>
                        <small><?= !empty($machine['antivirus_installed']) ? 'Verificado' : 'Pendente' ?></small>
                    </div>
                    <div class="<?= !empty($machine['requester_in_tflux']) ? 'ok' : '' ?>">
                        <?= icon(!empty($machine['requester_in_tflux']) ? 'check-circle' : 'circle') ?>
                        <span>Solicitante no TFlux</span>
                        <small><?= !empty($machine['requester_in_tflux']) ? 'Verificado' : 'Pendente' ?></small>
                    </div>
                </div>
            </article>
        <?php endif; ?>

        <?php if (!empty($machine['is_active'])): ?>
            <article class="danger-panel">
                <h2><?= icon('warning') ?> Zona de risco</h2>
                <p>A desativacao remove o dispositivo da listagem principal sem apagar o historico.</p>
                <form action="/?route=machines.deactivate&id=<?= (int) ($machine['id'] ?? 0) ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-danger btn-full" type="submit"><?= icon('trash-2') ?><span>Desativar dispositivo</span></button>
                </form>
            </article>
        <?php endif; ?>

        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('history') ?>
                    <h2>Historico recente</h2>
                </div>
            </header>

            <?php if (!$history): ?>
                <div class="empty-state compact">
                    <h3>Nenhum historico encontrado</h3>
                    <p>As proximas alteracoes aparecerao aqui.</p>
                </div>
            <?php else: ?>
                <div class="asset-timeline">
                    <?php foreach ($history as $event): ?>
                        <article>
                            <span></span>
                            <div>
                                <strong><?= e($event['description'] ?? '') ?></strong>
                                <small><?= e($event['created_at'] ?? '') ?> - <?= e(($event['user_name'] ?? '') ?: 'Sem usuario') ?></small>
                                <?php if (!empty($event['old_data']) || !empty($event['new_data'])): ?>
                                    <details>
                                        <summary>Ver dados</summary>
                                        <div class="json-grid">
                                            <pre><?= e(pretty_json($event['old_data'] ?? null)) ?></pre>
                                            <pre><?= e(pretty_json($event['new_data'] ?? null)) ?></pre>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>
    </aside>
</section>

<div class="lightbox" data-lightbox hidden>
    <div class="lightbox-topbar">
        <div>
            <strong data-lightbox-title>Foto do dispositivo</strong>
            <span data-lightbox-meta></span>
        </div>
        <div class="lightbox-actions">
            <a class="lightbox-action" href="#" download data-lightbox-download><?= icon('download') ?><span>Download</span></a>
            <button class="lightbox-close" type="button" data-lightbox-close aria-label="Fechar"><?= icon('chevron-right') ?></button>
        </div>
    </div>
    <button class="lightbox-nav prev" type="button" data-lightbox-prev aria-label="Foto anterior"><?= icon('chevron-left') ?></button>
    <figure class="lightbox-stage">
        <img src="" alt="" data-lightbox-img>
    </figure>
    <button class="lightbox-nav next" type="button" data-lightbox-next aria-label="Proxima foto"><?= icon('chevron-right') ?></button>
    <div class="lightbox-thumbs" data-lightbox-thumbs></div>
</div>
