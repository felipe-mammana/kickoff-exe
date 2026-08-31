<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Home</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <a href="/?route=companies.index">Empresas</a>
    <?= icon('chevron-right', 'breadcrumb-icon') ?>
    <span><?= e($company['name']) ?></span>
</nav>

<section class="device-hero">
    <div>
        <div class="device-kicker">
            <span class="asset-type"><?= icon('building-2') ?>Empresa</span>
            <span class="status-badge <?= $company['is_active'] ? 'ok' : 'no' ?>"><?= $company['is_active'] ? 'Ativa' : 'Inativa' ?></span>
        </div>
        <h1><?= e($company['name']) ?></h1>
        <p>Padrao de etiqueta: <span class="mono"><?= e($company['tag_pattern'] ?: '-') ?></span></p>
    </div>
    <div class="heading-actions">
        <a class="btn btn-muted" href="/?route=companies.index"><?= icon('chevron-left') ?><span>Voltar</span></a>
        <a class="btn btn-primary" href="/?route=companies.edit&id=<?= (int) $company['id'] ?>"><?= icon('edit-3') ?><span>Editar</span></a>
        <?php if (!empty($company['is_active'])): ?>
            <form action="/?route=companies.deactivate&id=<?= (int) $company['id'] ?>" method="post" data-confirm="Desativar esta empresa?" data-confirm-variant="warning">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-warning" type="submit"><?= icon('warning') ?><span>Desativar</span></button>
            </form>
        <?php else: ?>
            <form action="/?route=companies.reactivate&id=<?= (int) $company['id'] ?>" method="post" data-confirm="Reativar esta empresa?" data-confirm-variant="primary">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-primary" type="submit"><?= icon('check-circle') ?><span>Reativar</span></button>
            </form>
        <?php endif; ?>
        <form action="/?route=companies.destroy&id=<?= (int) $company['id'] ?>" method="post" data-confirm="Excluir totalmente esta empresa? Esta ação apaga a empresa e seus dados vinculados.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-danger" type="submit"><?= icon('trash-2') ?><span>Excluir total</span></button>
        </form>
    </div>
</section>

<section class="device-summary-grid">
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon('building-2') ?></span>
        <div>
            <small>Empresa</small>
            <strong><?= e($company['name']) ?></strong>
        </div>
    </article>
    <article class="summary-chip-card">
        <span class="metric-icon"><?= icon('tag') ?></span>
        <div>
            <small>Padrao etiqueta</small>
            <strong class="mono"><?= e($company['tag_pattern'] ?: '-') ?></strong>
        </div>
    </article>
    <article class="summary-chip-card">
        <span class="metric-icon <?= $company['is_active'] ? 'success' : 'warning' ?>"><?= icon($company['is_active'] ? 'check-circle' : 'warning') ?></span>
        <div>
            <small>Status</small>
            <strong><?= $company['is_active'] ? 'Ativa' : 'Inativa' ?></strong>
        </div>
    </article>
</section>

<section class="device-detail-grid">
    <div class="device-main-column">
        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('file-text') ?>
                    <h2>Dados da empresa</h2>
                </div>
            </header>

            <dl class="asset-property-grid">
                <div><dt>Nome</dt><dd><?= e($company['name']) ?></dd></div>
                <div><dt>Padrao de etiqueta</dt><dd class="mono"><?= e($company['tag_pattern'] ?: '-') ?></dd></div>
                <div><dt>Status</dt><dd><?= $company['is_active'] ? 'Ativa' : 'Inativa' ?></dd></div>
                <div><dt>Data de cadastro</dt><dd><?= e($company['created_at'] ?: '-') ?></dd></div>
                <div><dt>Usuário que cadastrou</dt><dd><?= e($company['created_by_name'] ?: '-') ?></dd></div>
                <div><dt>Última alteração</dt><dd><?= e($company['updated_at'] ?: '-') ?></dd></div>
                <div><dt>Usuário que alterou</dt><dd><?= e($company['updated_by_name'] ?: '-') ?></dd></div>
            </dl>
        </article>

        <article class="asset-panel company-attachments-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('file-text') ?>
                    <h2>Anexos da empresa</h2>
                </div>
                <div class="panel-actions">
                    <span><?= count($attachments ?? []) ?> arquivo(s)</span>
                    <button class="btn btn-muted" type="button" data-attachment-form-toggle aria-expanded="false">
                        <?= icon('plus') ?><span>Adicionar anexo</span>
                    </button>
                </div>
            </header>

            <form class="company-attachment-form" action="/?route=companies.attachments.store" method="post" enctype="multipart/form-data" data-attachment-form-panel hidden>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
                <div class="attachment-form-main">
                    <label class="upload-drop compact">
                        <span class="upload-icon"><?= icon('file-text') ?></span>
                        <strong>Selecionar arquivo</strong>
                        <small>PDF, Office, CSV, TXT, imagem ou ZIP até <?= e(format_file_size(COMPANY_ATTACHMENT_MAX_BYTES)) ?></small>
                        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.webp,.zip" required data-attachment-input>
                    </label>
                    <label class="field">
                        <span>Descrição opcional</span>
                        <input type="text" name="description" maxlength="255" placeholder="Ex.: contrato, proposta, evidências">
                    </label>
                </div>
                <div class="attachment-form-footer">
                    <div class="attachment-selected" data-attachment-selected hidden>
                        <?= icon('file-check') ?>
                        <span data-attachment-name>Arquivo selecionado</span>
                        <button type="button" data-attachment-clear aria-label="Remover arquivo selecionado"><?= icon('x') ?></button>
                    </div>
                    <button class="btn btn-primary" type="submit"><?= icon('upload') ?><span>Enviar anexo</span></button>
                </div>
            </form>

            <?php if (empty($attachments)): ?>
                <div class="empty-state compact">
                    <h3>Nenhum anexo cadastrado</h3>
                    <p>Envie arquivos importantes da empresa para consulta interna.</p>
                </div>
            <?php else: ?>
                <div class="inventory-table-wrap">
                    <table class="inventory-table company-attachments-table">
                        <thead>
                            <tr>
                                <th>Arquivo</th>
                                <th>Descrição</th>
                                <th>Tamanho</th>
                                <th>Enviado por</th>
                                <th>Data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachments as $attachment): ?>
                                <tr>
                                    <td data-label="Arquivo">
                                        <strong><?= e($attachment['original_name']) ?></strong>
                                    </td>
                                    <td data-label="Descrição"><?= e($attachment['description'] ?: '-') ?></td>
                                    <td data-label="Tamanho"><?= e(format_file_size((int) $attachment['file_size'])) ?></td>
                                    <td data-label="Enviado por"><?= e($attachment['uploaded_by_name'] ?: '-') ?></td>
                                    <td data-label="Data"><?= e($attachment['created_at'] ?: '-') ?></td>
                                    <td data-label="Ações">
                                        <div class="table-actions">
                                            <a class="icon-btn" href="/?route=companies.attachments.download&id=<?= (int) $attachment['id'] ?>" aria-label="Baixar anexo" title="Baixar">
                                                <?= icon('download') ?>
                                            </a>
                                            <form action="/?route=companies.attachments.delete" method="post" data-confirm="Remover este anexo?">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= (int) $attachment['id'] ?>">
                                                <button class="icon-btn danger" type="submit" aria-label="Remover anexo" title="Remover">
                                                    <?= icon('trash-2') ?>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>
    </div>

    <aside class="device-side-column">
        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('monitor-cog') ?>
                    <h2>Inventario</h2>
                </div>
            </header>
            <div class="quick-panel-body">
                <p>Veja os dispositivos vinculados a esta empresa e cadastre novos ativos usando o padrão definido.</p>
                <a class="btn btn-primary btn-full" href="/?company_id=<?= (int) $company['id'] ?>"><?= icon('monitor-cog') ?><span>Ver dispositivos</span></a>
            </div>
        </article>

        <?php if (!empty($company['is_active'])): ?>
            <article class="danger-panel">
                <h2><?= icon('warning') ?> Zona de risco</h2>
                <p>A desativação remove a empresa dos fluxos principais sem apagar seu histórico.</p>
                <form action="/?route=companies.deactivate&id=<?= (int) $company['id'] ?>" method="post" data-confirm="Desativar esta empresa?" data-confirm-variant="warning">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-warning btn-full" type="submit"><?= icon('warning') ?><span>Desativar empresa</span></button>
                </form>
            </article>
        <?php else: ?>
            <article class="danger-panel">
                <h2><?= icon('check-circle') ?> Empresa inativa</h2>
                <p>Reative a empresa para permitir novos cadastros de dispositivos.</p>
                <form action="/?route=companies.reactivate&id=<?= (int) $company['id'] ?>" method="post" data-confirm="Reativar esta empresa?" data-confirm-variant="primary">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-primary btn-full" type="submit"><?= icon('check-circle') ?><span>Reativar empresa</span></button>
                </form>
                <form action="/?route=companies.destroy&id=<?= (int) $company['id'] ?>" method="post" data-confirm="Excluir totalmente esta empresa? Esta ação apaga a empresa e seus dados vinculados.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn btn-danger btn-full" type="submit"><?= icon('trash-2') ?><span>Excluir totalmente</span></button>
                </form>
            </article>
        <?php endif; ?>

        <article class="asset-panel">
            <header class="asset-panel-head">
                <div>
                    <?= icon('history') ?>
                    <h2>Histórico da empresa</h2>
                </div>
                <span><?= count($history) ?> evento(s)</span>
            </header>

            <?php if (!$history): ?>
                <div class="empty-state compact">
                    <h3>Nenhum historico encontrado</h3>
                    <p>As próximas alterações desta empresa aparecerão aqui.</p>
                </div>
            <?php else: ?>
                <div class="asset-timeline">
                    <?php foreach ($history as $event): ?>
                        <article>
                            <span></span>
                            <div>
                                <strong><?= e($event['description']) ?></strong>
                                <small><?= e($event['created_at']) ?> · <?= e($event['user_name'] ?: 'Sem usuário') ?></small>
                                <?php if ($event['old_data'] || $event['new_data']): ?>
                                    <details>
                                        <summary>Ver alterações</summary>
                                        <div class="json-grid">
                                            <pre><?= e(pretty_json($event['old_data'])) ?></pre>
                                            <pre><?= e(pretty_json($event['new_data'])) ?></pre>
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
