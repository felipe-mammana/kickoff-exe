<?php
$isEdit = !empty($machine['id']);
$checked = static fn (string $field): string => !empty($machine[$field]) ? 'checked' : '';
$value = static function (string $field) use ($machine): string {
    $fallbacks = [
        'brand' => ['brand', 'printer_brand'],
        'operating_system' => ['operating_system'],
    ];

    foreach ($fallbacks[$field] ?? [$field] as $candidate) {
        if (!empty($machine[$candidate])) {
            return e((string) $machine[$candidate]);
        }
    }

    return '';
};
$selectedType = (string) ($machine['device_type'] ?? 'notebook');
$fieldClass = static fn (array $errors, string $field): string => isset($errors[$field]) ? 'field has-error' : 'field';
$typeIcons = [
    'notebook' => 'laptop',
    'cpu' => 'monitor-cog',
    'roteador' => 'router',
    'access_point' => 'router',
    'modem' => 'router',
    'impressora' => 'printer',
    'outros' => 'settings',
];
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?company_id=<?= (int) ($company['id'] ?? $machine['company_id'] ?? 0) ?>">Dispositivos</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span><?= $isEdit ? 'Editar cadastro' : 'Novo cadastro' ?></span>
</nav>

<section class="asset-page-head">
    <div>
        <h1><?= $isEdit ? 'Editar dispositivo' : 'Novo cadastro de dispositivo' ?></h1>
        <p>Insira as informacoes tecnicas, vincule a empresa e registre evidencias do ativo.</p>
    </div>
    <a class="btn btn-muted" href="<?= $isEdit ? '/?route=machines.show&id=' . (int) $machine['id'] : '/?company_id=' . (int) ($company['id'] ?? 0) ?>">
        <?= icon('eye') ?><span>Voltar</span>
    </a>
</section>

<?php if ($errors): ?>
    <div class="alert alert-danger">Revise os campos destacados antes de salvar.</div>
<?php endif; ?>

<form class="asset-device-form" action="<?= e($action) ?>" method="post" enctype="multipart/form-data" novalidate data-device-form>
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="company_id" value="<?= (int) ($company['id'] ?? $machine['company_id'] ?? 0) ?>">

    <div class="form-flow">
        <section class="asset-form-section">
            <header class="asset-section-head">
                <span class="step-number">1</span>
                <div>
                    <h2>Selecao inicial</h2>
                    <p>Escolha o tipo do dispositivo para adaptar o formulario automaticamente.</p>
                </div>
            </header>

            <div class="asset-selection-grid">
                <label class="field">
                    <span>Empresa titular</span>
                    <input type="text" value="<?= e($company['name'] ?? '') ?>" disabled>
                    <?php if (!empty($company['tag_pattern'])): ?>
                        <small class="field-hint">Padrao de etiqueta: <?= e($company['tag_pattern']) ?></small>
                    <?php endif; ?>
                </label>

                <label class="<?= $fieldClass($errors, 'device_type') ?>">
                    <span>Tipo de dispositivo</span>
                    <select name="device_type" data-device-type required>
                        <?php foreach ($deviceTypes as $type => $label): ?>
                            <option value="<?= e($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="device-type-grid" data-device-type-grid>
                <?php foreach ($deviceTypes as $type => $label): ?>
                    <button class="device-type-card <?= $selectedType === $type ? 'active' : '' ?>" type="button" data-device-type-card="<?= e($type) ?>">
                        <?= icon($typeIcons[$type] ?? 'settings') ?>
                        <span><?= e($label) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="asset-form-section">
            <header class="asset-section-head">
                <span class="step-number">2</span>
                <div>
                    <h2>Identificacao do ativo</h2>
                    <p>Dados comuns para rastreio, etiqueta e localizacao no inventario.</p>
                </div>
            </header>

            <div class="fields-grid">
                <label class="<?= $fieldClass($errors, 'tag') ?>">
                    <span>Numero da etiqueta</span>
                    <input type="text" name="tag" value="<?= $value('tag') ?>" placeholder="<?= e($company['tag_pattern'] ?: 'NOTE-0001') ?>">
                    <?php if (isset($errors['tag'])): ?><small><?= e($errors['tag']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'old_hostname') ?>" data-device-section="notebook cpu">
                    <span>Hostname antigo</span>
                    <input type="text" name="old_hostname" value="<?= $value('old_hostname') ?>">
                    <?php if (isset($errors['old_hostname'])): ?><small><?= e($errors['old_hostname']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'new_hostname') ?>" data-device-section="notebook cpu">
                    <span>Hostname novo</span>
                    <input type="text" name="new_hostname" value="<?= $value('new_hostname') ?>">
                    <?php if (isset($errors['new_hostname'])): ?><small><?= e($errors['new_hostname']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>

        <section class="asset-form-section device-section" data-device-section="notebook cpu">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Dados do computador</h2>
                    <p>Campos tecnicos para notebook e CPU / desktop.</p>
                </div>
            </header>

            <div class="fields-grid three-cols">
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo do computador</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>" placeholder="Latitude 5420">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'employee_name') ?>">
                    <span>Colaborador que usa a maquina</span>
                    <input type="text" name="employee_name" value="<?= $value('employee_name') ?>">
                    <?php if (isset($errors['employee_name'])): ?><small><?= e($errors['employee_name']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'department') ?>">
                    <span>Departamento</span>
                    <input type="text" name="department" value="<?= $value('department') ?>">
                    <?php if (isset($errors['department'])): ?><small><?= e($errors['department']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'install_location') ?>">
                    <span>Local</span>
                    <input type="text" name="install_location" value="<?= $value('install_location') ?>" placeholder="Ex.: Sala TI, Recepcao, Financeiro">
                    <?php if (isset($errors['install_location'])): ?><small><?= e($errors['install_location']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'machine_password') ?>">
                    <span>Senha da maquina</span>
                    <span class="password-wrap">
                        <input type="password" name="machine_password" value="<?= $value('machine_password') ?>" data-password-input>
                        <button type="button" data-password-toggle>Ver</button>
                    </span>
                    <?php if (isset($errors['machine_password'])): ?><small><?= e($errors['machine_password']) ?></small><?php endif; ?>
                </label>
            </div>

            <div class="check-grid">
                <label class="check-card asset-check">
                    <input type="checkbox" name="tflux_installed" <?= $checked('tflux_installed') ?>>
                    <span>TFlux instalado</span>
                </label>
                <label class="check-card asset-check">
                    <input type="checkbox" name="antivirus_installed" <?= $checked('antivirus_installed') ?>>
                    <span>Antivirus instalado</span>
                </label>
                <label class="check-card asset-check">
                    <input type="checkbox" name="requester_in_tflux" <?= $checked('requester_in_tflux') ?>>
                    <span>Solicitante cadastrado no TFlux</span>
                </label>
            </div>
        </section>

        <section class="asset-form-section device-section" data-device-section="roteador modem">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Rede e administracao</h2>
                    <p>Credenciais, acesso e dados da operadora quando aplicavel.</p>
                </div>
            </header>

            <div class="fields-grid three-cols">
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'admin_user') ?>">
                    <span>Usuario admin</span>
                    <input type="text" name="admin_user" value="<?= $value('admin_user') ?>">
                    <?php if (isset($errors['admin_user'])): ?><small><?= e($errors['admin_user']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'admin_password') ?>">
                    <span>Senha admin</span>
                    <input type="text" name="admin_password" value="<?= $value('admin_password') ?>">
                    <?php if (isset($errors['admin_password'])): ?><small><?= e($errors['admin_password']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'ip_address') ?>" data-device-section="roteador">
                    <span>IP de acesso</span>
                    <input type="text" name="ip_address" value="<?= $value('ip_address') ?>" placeholder="192.168.0.1">
                    <?php if (isset($errors['ip_address'])): ?><small><?= e($errors['ip_address']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'carrier') ?>" data-device-section="modem">
                    <span>Operadora</span>
                    <input type="text" name="carrier" value="<?= $value('carrier') ?>">
                    <?php if (isset($errors['carrier'])): ?><small><?= e($errors['carrier']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>

        <section class="asset-form-section device-section" data-device-section="access_point">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Instalacao</h2>
                    <p>Localizacao fisica e modelo do Access Point.</p>
                </div>
            </header>

            <div class="fields-grid">
                <label class="<?= $fieldClass($errors, 'install_location') ?>">
                    <span>Local de instalacao</span>
                    <input type="text" name="install_location" value="<?= $value('install_location') ?>" placeholder="Sala de reuniao, recepcao, estoque">
                    <?php if (isset($errors['install_location'])): ?><small><?= e($errors['install_location']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>

        <section class="asset-form-section device-section" data-device-section="impressora">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Dados da impressora</h2>
                    <p>Conexao, compartilhamento e configuracao de rede.</p>
                </div>
            </header>

            <div class="fields-grid three-cols">
                <label class="<?= $fieldClass($errors, 'brand') ?>">
                    <span>Marca</span>
                    <input type="text" name="brand" value="<?= $value('brand') ?>">
                    <?php if (isset($errors['brand'])): ?><small><?= e($errors['brand']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'printer_connection_type') ?>">
                    <span>Tipo de conexao</span>
                    <select name="printer_connection_type" data-printer-connection>
                        <option value="">Selecione</option>
                        <option value="usb" <?= ($machine['printer_connection_type'] ?? '') === 'usb' ? 'selected' : '' ?>>USB</option>
                        <option value="rede" <?= ($machine['printer_connection_type'] ?? '') === 'rede' ? 'selected' : '' ?>>Rede</option>
                    </select>
                    <?php if (isset($errors['printer_connection_type'])): ?><small><?= e($errors['printer_connection_type']) ?></small><?php endif; ?>
                </label>
                <label class="field" data-printer-network>
                    <span>IP da impressora</span>
                    <input type="text" name="ip_address" value="<?= $value('ip_address') ?>" placeholder="192.168.0.45">
                </label>
                <label class="field" data-printer-network>
                    <span>Gateway</span>
                    <input type="text" name="gateway" value="<?= $value('gateway') ?>" placeholder="192.168.0.1">
                </label>
            </div>

            <label class="check-card asset-check" data-printer-usb>
                <input type="checkbox" name="printer_shared" <?= $checked('printer_shared') ?>>
                <span>Impressora compartilhada</span>
            </label>

            <div data-printer-network>
                <div class="upload-meta-grid compact">
                    <label class="field">
                        <span>Topico da foto de rede</span>
                        <select name="network_photo_topic">
                            <?php foreach (MachinePhoto::topics() as $topicValue => $topicLabel): ?>
                                <option value="<?= e($topicValue) ?>" <?= $topicValue === 'equipamento' ? 'selected' : '' ?>><?= e($topicLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field">
                        <span>Nome do local</span>
                        <input type="text" name="network_photo_location_name" placeholder="Ex.: Sala TI, Rack recepcao">
                    </label>
                </div>
                <div class="upload-choice-grid compact">
                    <label class="upload-drop compact">
                        <input type="file" name="network_photo[]" accept="image/jpeg,image/png,image/webp">
                        <span class="upload-icon"><?= icon('image') ?></span>
                        <strong>Escolher da galeria</strong>
                        <small>Use uma imagem ja salva da configuracao de rede.</small>
                    </label>
                    <label class="upload-drop compact">
                        <input type="file" name="network_photo[]" accept="image/jpeg,image/png,image/webp" capture="environment">
                        <span class="upload-icon"><?= icon('camera') ?></span>
                        <strong>Tirar foto agora</strong>
                        <small>Abra a camera para registrar IP, gateway ou pagina de configuracao.</small>
                    </label>
                </div>
            </div>
        </section>

        <section class="asset-form-section device-section" data-device-section="outros">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Outros dispositivos</h2>
                    <p>Ficha flexivel para ativos fora das categorias padrao.</p>
                </div>
            </header>

            <div class="fields-grid three-cols">
                <label class="field">
                    <span>Marca</span>
                    <input type="text" name="brand" value="<?= $value('brand') ?>">
                </label>
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
                <label class="field">
                    <span>Local / responsavel</span>
                    <input type="text" name="install_location" value="<?= $value('install_location') ?>">
                </label>
            </div>
        </section>

        <section class="asset-form-section photo-section">
            <header class="asset-section-head">
                <span class="step-number">4</span>
                <div>
                    <h2>Fotos e observacoes</h2>
                    <p>Registre evidencias do ativo usando camera ou galeria.</p>
                </div>
            </header>

            <div class="photo-layout">
                <div class="photo-upload-column">
                    <div class="upload-meta-grid">
                        <label class="field">
                            <span>Topico das fotos</span>
                            <select name="photo_topic">
                                <?php foreach (MachinePhoto::topics() as $topicValue => $topicLabel): ?>
                                    <option value="<?= e($topicValue) ?>" <?= $topicValue === 'equipamento' ? 'selected' : '' ?>><?= e($topicLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Nome do local</span>
                            <input type="text" name="photo_location_name" placeholder="Ex.: Escritorio, Recepcao, Rack">
                        </label>
                    </div>
                    <div class="upload-choice-grid">
                        <label class="upload-drop asset-upload">
                            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-photo-primary>
                            <span class="upload-icon"><?= icon('image') ?></span>
                            <strong>Escolher da galeria</strong>
                            <small>Selecione uma ou varias imagens salvas no celular.</small>
                        </label>
                        <label class="upload-drop asset-upload">
                            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" capture="environment" data-photo-input>
                            <span class="upload-icon"><?= icon('camera') ?></span>
                            <strong>Tirar foto agora</strong>
                            <small>Abra a camera e registre uma nova evidencia.</small>
                        </label>
                    </div>
                    <label class="<?= $fieldClass($errors, 'notes') ?>">
                        <span>Observacoes</span>
                        <textarea name="notes" rows="5"><?= $value('notes') ?></textarea>
                        <?php if (isset($errors['notes'])): ?><small><?= e($errors['notes']) ?></small><?php endif; ?>
                    </label>
                </div>

                <div class="photo-preview-column">
                    <div class="preview-grid" data-photo-preview></div>
                    <?php if ($photos): ?>
                        <div class="photo-grid existing">
                            <?php foreach ($photos as $photo): ?>
                                <figure>
                                    <img src="<?= e(UPLOAD_URL . '/' . $photo['file_name']) ?>" alt="<?= e($photo['original_name']) ?>">
                                    <figcaption>
                                        <span>
                                            <?= e(MachinePhoto::topicLabel($photo['photo_topic'] ?? 'equipamento')) ?>
                                            <?= !empty($photo['location_name']) ? ' - ' . e($photo['location_name']) : '' ?>
                                            <?= ($photo['photo_type'] ?? 'general') === 'network_config' ? ' - Rede' : '' ?>
                                            : <?= e($photo['original_name']) ?>
                                        </span>
                                        <button class="link-danger" type="submit" form="delete-photo-<?= (int) $photo['id'] ?>"><?= icon('trash-2') ?><span>Remover</span></button>
                                    </figcaption>
                                </figure>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="photo-placeholder" data-photo-empty>
                            <?= icon('camera') ?>
                            <span>As fotos selecionadas aparecerao aqui.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="form-actions-bar">
            <a class="btn btn-muted" href="<?= $isEdit ? '/?route=machines.show&id=' . (int) $machine['id'] : '/?company_id=' . (int) ($company['id'] ?? 0) ?>">Cancelar</a>
            <button class="btn btn-primary" type="submit"><?= icon('save') ?><span><?= $isEdit ? 'Salvar alteracoes' : 'Salvar dispositivo' ?></span></button>
        </div>
    </div>
</form>

<?php foreach ($photos as $photo): ?>
    <form id="delete-photo-<?= (int) $photo['id'] ?>" action="/?route=machines.deletePhoto" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
    </form>
<?php endforeach; ?>
