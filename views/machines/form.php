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
$companyTagCode = Company::tagCode($company ?? null);
$currentTagPrefix = Machine::tagPrefix($selectedType, $company ?? null);
$currentTagNumber = Machine::extractTagNumber($machine['tag'] ?? null, $selectedType, $company ?? null);
$isFreeTag = $currentTagPrefix === null;
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
        <p>Insira as informações técnicas, vincule a empresa e registre evidências do ativo.</p>
    </div>
    <a class="btn btn-muted" href="<?= $isEdit ? '/?route=machines.show&id=' . (int) $machine['id'] : '/?company_id=' . (int) ($company['id'] ?? 0) ?>">
        <?= icon('arrow-left') ?><span>Voltar</span>
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
                    <p>Escolha o tipo do dispositivo para adaptar o formulário automaticamente.</p>
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
            </div>

            <input type="hidden" name="device_type" value="<?= e($selectedType) ?>" data-device-type required>

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
                    <h2>Identificação do ativo</h2>
                    <p>Dados comuns para rastreio, etiqueta e localização no inventário.</p>
                </div>
            </header>

            <div class="fields-grid single-col">
                <label class="<?= $fieldClass($errors, 'tag') ?>" data-tag-field-wrapper>
                    <span data-tag-label><?= $isFreeTag ? 'Etiqueta' : 'Numero da etiqueta' ?></span>
                    <input type="hidden" name="tag" value="<?= $value('tag') ?>" data-tag-full>
                    <span class="tag-mask-control" data-company-tag-code="<?= e($companyTagCode) ?>" data-tag-mask-wrap <?= $isFreeTag ? 'hidden' : '' ?>>
                        <strong data-tag-prefix><?= e($currentTagPrefix ?: '') ?></strong>
                        <input type="text" inputmode="numeric" pattern="[0-9]*" data-tag-number value="<?= e($currentTagNumber) ?>" placeholder="001" aria-label="Numero da etiqueta" <?= $isFreeTag ? 'disabled' : '' ?>>
                    </span>
                    <input type="text" data-tag-free value="<?= $isFreeTag ? $value('tag') : '' ?>" placeholder="Ex.: AP-001" aria-label="Etiqueta do dispositivo" <?= !$isFreeTag ? 'hidden disabled' : '' ?>>
                    <?php if (isset($errors['tag'])): ?><small><?= e($errors['tag']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>

        <section class="asset-form-section device-section" data-device-section="notebook cpu">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Dados do computador</h2>
                    <p>Campos técnicos para notebook e CPU / desktop.</p>
                </div>
            </header>

            <div class="fields-grid three-cols">
                <label class="<?= $fieldClass($errors, 'old_hostname') ?>">
                    <span>Hostname antigo</span>
                    <input type="text" name="old_hostname" value="<?= $value('old_hostname') ?>">
                    <?php if (isset($errors['old_hostname'])): ?><small><?= e($errors['old_hostname']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'new_hostname') ?>">
                    <span>Hostname novo</span>
                    <input type="text" name="new_hostname" value="<?= $value('new_hostname') ?>">
                    <?php if (isset($errors['new_hostname'])): ?><small><?= e($errors['new_hostname']) ?></small><?php endif; ?>
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
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>" placeholder="Opcional">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'install_location') ?>">
                    <span>Local</span>
                    <input type="text" name="install_location" value="<?= $value('install_location') ?>" placeholder="Ex.: Sala TI, Recepcao, Financeiro">
                    <?php if (isset($errors['install_location'])): ?><small><?= e($errors['install_location']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'machine_password') ?>">
                    <span>Senha da máquina</span>
                    <span class="password-wrap">
                        <input type="password" name="machine_password" value="" placeholder="<?= $isEdit ? 'Preencha apenas para alterar' : '' ?>" data-password-input>
                        <button type="button" data-password-toggle>Ver</button>
                    </span>
                    <?php if ($isEdit): ?><small class="field-hint">Deixe em branco para manter a senha cadastrada.</small><?php endif; ?>
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
                    <h2>Rede e administração</h2>
                    <p>Credenciais, acesso e dados da operadora quando aplicável.</p>
                </div>
            </header>

            <div class="fields-grid three-cols">
                <label class="<?= $fieldClass($errors, 'computer_model') ?>">
                    <span>Modelo</span>
                    <input type="text" name="computer_model" value="<?= $value('computer_model') ?>">
                    <?php if (isset($errors['computer_model'])): ?><small><?= e($errors['computer_model']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'admin_user') ?>">
                    <span>Usuário admin</span>
                    <input type="text" name="admin_user" value="<?= $value('admin_user') ?>">
                    <?php if (isset($errors['admin_user'])): ?><small><?= e($errors['admin_user']) ?></small><?php endif; ?>
                </label>
                <label class="<?= $fieldClass($errors, 'admin_password') ?>">
                    <span>Senha admin</span>
                    <input type="password" name="admin_password" value="" placeholder="<?= $isEdit ? 'Preencha apenas para alterar' : '' ?>">
                    <?php if ($isEdit): ?><small class="field-hint">Deixe em branco para manter a senha cadastrada.</small><?php endif; ?>
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
                    <h2>Instalação</h2>
                    <p>Localização física e modelo do Access Point.</p>
                </div>
            </header>

            <div class="fields-grid">
                <label class="<?= $fieldClass($errors, 'install_location') ?>">
                    <span>Local de instalação</span>
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
                    <p>Conexão, compartilhamento e configuração de rede.</p>
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
        </section>

        <section class="asset-form-section device-section" data-device-section="outros">
            <header class="asset-section-head">
                <span class="step-number">3</span>
                <div>
                    <h2>Outros dispositivos</h2>
                    <p>Ficha flexível para ativos fora das categorias padrão.</p>
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
                    <h2>Fotos e observações</h2>
                    <p>Registre evidências do ativo usando câmera ou galeria.</p>
                </div>
            </header>

            <div class="photo-layout">
                <div class="photo-upload-column">
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
                            <small>Abra a câmera e registre uma nova evidência.</small>
                        </label>
                    </div>
                    <label class="<?= $fieldClass($errors, 'notes') ?>">
                        <span>Observações</span>
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
                                    <img src="<?= e(upload_file_url((string) $photo['file_name'])) ?>" alt="<?= e($photo['original_name']) ?>">
                                    <figcaption>
                                        <span>
                                            <?= e(MachinePhoto::topicLabel($photo['photo_topic'] ?? 'equipamento')) ?>
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
            <button class="btn btn-primary" type="submit"><?= icon('save') ?><span><?= $isEdit ? 'Salvar alterações' : 'Salvar dispositivo' ?></span></button>
        </div>
    </div>
</form>

<?php foreach ($photos as $photo): ?>
    <form id="delete-photo-<?= (int) $photo['id'] ?>" action="/?route=machines.deletePhoto" method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
    </form>
<?php endforeach; ?>
