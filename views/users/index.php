<?php
$errors = $errors ?? [];
$old = $old ?? [];
$openModal = $openModal ?? '';
$adminCount = 0;
$activeCount = 0;

foreach ($users as $user) {
    if (!empty($user['is_admin'])) {
        $adminCount++;
    }
    if (!empty($user['is_active'])) {
        $activeCount++;
    }
}

$standardCount = count($users) - $adminCount;
$fieldError = static fn (string $field): string => isset($errors[$field]) ? '<small>' . e($errors[$field]) . '</small>' : '';
$oldValue = static fn (string $field, string $default = ''): string => e((string) ($old[$field] ?? $default));
?>

<nav class="breadcrumbs" aria-label="Breadcrumb">
    <a href="/">Dashboard</a>
    <span><?= icon('chevron-right') ?></span>
    <strong>Usuários</strong>
</nav>

<section class="asset-page-head">
    <div>
        <span class="eyebrow">Administração</span>
        <h1>Usuários</h1>
        <p>Contas com acesso ao inventário, separadas por perfil administrativo e operacional.</p>
    </div>
    <div class="header-actions">
        <a class="btn btn-muted" href="/?route=audit.index"><?= icon('file-clock') ?><span>Ver logs</span></a>
        <button class="btn btn-primary" type="button" data-user-modal-open="create"><?= icon('plus') ?><span>Novo usuário</span></button>
    </div>
</section>

<section class="audit-summary-grid user-summary-grid">
    <article class="summary-card">
        <span class="summary-icon"><?= icon('users') ?></span>
        <div>
            <strong><?= count($users) ?></strong>
            <span>contas cadastradas</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('check-circle') ?></span>
        <div>
            <strong><?= $activeCount ?></strong>
            <span>contas ativas</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('settings') ?></span>
        <div>
            <strong><?= $adminCount ?></strong>
            <span>administradores</span>
        </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('users') ?></span>
        <div>
            <strong><?= $standardCount ?></strong>
            <span>usuários padrão</span>
        </div>
    </article>
</section>

<section class="asset-panel user-table-panel">
    <header class="asset-panel-head">
        <div>
            <span><?= icon('users') ?></span>
            <div>
                <h2>Acessos cadastrados</h2>
                <p>Crie, edite, desative ou redefina senhas de acesso.</p>
            </div>
        </div>
        <div class="export-actions" data-export-actions>
            <span class="status-chip neutral"><?= count($users) ?> contas</span>
            <a class="btn btn-muted export-btn <?= !$users ? 'disabled' : '' ?>" href="<?= e(export_url('users', 'csv')) ?>" data-export-link data-export-format="CSV" aria-disabled="<?= !$users ? 'true' : 'false' ?>">
                <?= icon('file-spreadsheet') ?><span>CSV</span>
            </a>
            <a class="btn btn-muted export-btn <?= !$users ? 'disabled' : '' ?>" href="<?= e(export_url('users', 'json')) ?>" data-export-link data-export-format="JSON" aria-disabled="<?= !$users ? 'true' : 'false' ?>">
                <?= icon('braces') ?><span>JSON</span>
            </a>
        </div>
    </header>

    <?php if (!$users): ?>
        <div class="empty-state compact audit-empty">
            <span class="empty-icon"><?= icon('users') ?></span>
            <h3>Nenhum usuario cadastrado</h3>
            <p>Cadastre uma conta administrativa antes de liberar o sistema.</p>
            <button class="btn btn-primary" type="button" data-user-modal-open="create"><?= icon('plus') ?><span>Novo usuário</span></button>
        </div>
    <?php else: ?>
        <div class="inventory-table-wrap">
            <table class="inventory-table user-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>E-mail</th>
                        <th>Perfil</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $name = (string) $user['name'];
                        $initial = strtoupper(substr(trim($name) !== '' ? trim($name) : 'U', 0, 1));
                        $isAdmin = !empty($user['is_admin']);
                        $isActive = !empty($user['is_active']);
                        $isSelf = (int) $user['id'] === (int) current_user()['id'];
                        ?>
                        <tr>
                            <td data-label="Usuário">
                                <div class="audit-user-cell">
                                    <span class="audit-avatar"><?= e($initial) ?></span>
                                    <div>
                                        <strong><?= e($name) ?></strong>
                                        <small>ID #<?= (int) $user['id'] ?><?= $isSelf ? ' - você' : '' ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="E-mail">
                                <span class="user-email"><?= e($user['email']) ?></span>
                            </td>
                            <td data-label="Perfil">
                                <span class="audit-action-badge <?= $isAdmin ? 'info' : 'neutral' ?>">
                                    <?= icon($isAdmin ? 'settings' : 'users') ?>
                                    <?= $isAdmin ? 'Administrador' : 'Usuário padrão' ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="status-chip <?= $isActive ? 'success' : 'neutral' ?>"><?= icon($isActive ? 'check-circle' : 'warning') ?><span><?= $isActive ? 'Ativo' : 'Inativo' ?></span></span>
                            </td>
                            <td data-label="Ações">
                                <div class="row-actions">
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        data-user-modal-open="edit"
                                        data-user-id="<?= (int) $user['id'] ?>"
                                        data-user-name="<?= e($user['name']) ?>"
                                        data-user-email="<?= e($user['email']) ?>"
                                        data-user-admin="<?= $isAdmin ? '1' : '0' ?>"
                                        aria-label="Editar usuario"
                                        title="Editar usuario"
                                    ><?= icon('edit-3') ?></button>
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        data-user-modal-open="password"
                                        data-user-id="<?= (int) $user['id'] ?>"
                                        data-user-name="<?= e($user['name']) ?>"
                                        aria-label="Redefinir senha"
                                        title="Redefinir senha"
                                    ><?= icon('eye') ?></button>
                                    <form action="/?route=users.setStatus" method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $isActive ? 'inactive' : 'active' ?>">
                                        <button class="icon-btn" type="submit" aria-label="<?= $isActive ? 'Desativar usuario' : 'Ativar usuario' ?>" title="<?= $isActive ? 'Desativar usuario' : 'Ativar usuario' ?>" <?= $isSelf && $isActive ? 'disabled' : '' ?>>
                                            <?= icon($isActive ? 'trash-2' : 'check-circle') ?>
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
</section>

<div class="company-modal user-modal" data-user-modal="create" <?= $openModal === 'create' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-create-title">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('users') ?></span>
                <h2 id="user-create-title">Novo usuario</h2>
            </div>
            <button class="icon-btn" type="button" data-user-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form" action="/?route=users.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="field <?= isset($errors['name']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <span>Nome</span>
                <input type="text" name="name" value="<?= $openModal === 'create' ? $oldValue('name') : '' ?>" required data-user-modal-focus>
                <?= $openModal === 'create' ? $fieldError('name') : '' ?>
            </label>
            <label class="field <?= isset($errors['email']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <span>E-mail</span>
                <input type="email" name="email" value="<?= $openModal === 'create' ? $oldValue('email') : '' ?>" required>
                <?= $openModal === 'create' ? $fieldError('email') : '' ?>
            </label>
            <label class="field <?= isset($errors['password']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <span>Senha inicial</span>
                <input type="password" name="password" minlength="8" required>
                <?= $openModal === 'create' ? $fieldError('password') : '' ?>
            </label>
            <label class="field <?= isset($errors['password_confirmation']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <span>Confirmar senha</span>
                <input type="password" name="password_confirmation" minlength="8" required>
                <?= $openModal === 'create' ? $fieldError('password_confirmation') : '' ?>
            </label>
            <label class="check-card">
                <input type="checkbox" name="is_admin" <?= !empty($old['is_admin']) && $openModal === 'create' ? 'checked' : '' ?>>
                <span>Administrador</span>
            </label>
            <footer class="form-actions">
                <button class="btn btn-muted" type="button" data-user-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </footer>
        </form>
    </div>
</div>

<div class="company-modal user-modal" data-user-modal="edit" <?= $openModal === 'edit' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-edit-title">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('edit-3') ?></span>
                <h2 id="user-edit-title">Editar usuario</h2>
            </div>
            <button class="icon-btn" type="button" data-user-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form" action="/?route=users.update" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $openModal === 'edit' ? (int) ($old['id'] ?? 0) : '' ?>" data-user-edit-id>
            <label class="field <?= isset($errors['name']) && $openModal === 'edit' ? 'has-error' : '' ?>">
                <span>Nome</span>
                <input type="text" name="name" value="<?= $openModal === 'edit' ? $oldValue('name') : '' ?>" required data-user-edit-name>
                <?= $openModal === 'edit' ? $fieldError('name') : '' ?>
            </label>
            <label class="field <?= isset($errors['email']) && $openModal === 'edit' ? 'has-error' : '' ?>">
                <span>E-mail</span>
                <input type="email" name="email" value="<?= $openModal === 'edit' ? $oldValue('email') : '' ?>" required data-user-edit-email>
                <?= $openModal === 'edit' ? $fieldError('email') : '' ?>
            </label>
            <label class="check-card <?= isset($errors['is_admin']) && $openModal === 'edit' ? 'has-error' : '' ?>">
                <input type="checkbox" name="is_admin" <?= !empty($old['is_admin']) && $openModal === 'edit' ? 'checked' : '' ?> data-user-edit-admin>
                <span>Administrador</span>
            </label>
            <?php if ($openModal === 'edit'): ?>
                <?= $fieldError('is_admin') ?>
            <?php endif; ?>
            <footer class="form-actions">
                <button class="btn btn-muted" type="button" data-user-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </footer>
        </form>
    </div>
</div>

<div class="company-modal user-modal" data-user-modal="password" <?= $openModal === 'password' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-password-title">
        <header class="asset-panel-head">
            <div>
                <span><?= icon('eye') ?></span>
                <h2 id="user-password-title">Redefinir senha</h2>
            </div>
            <button class="icon-btn" type="button" data-user-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form" action="/?route=users.resetPassword" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $openModal === 'password' ? (int) ($old['id'] ?? 0) : '' ?>" data-user-password-id>
            <p class="field-hint">Usuário: <strong data-user-password-name><?= e((string) ($old['name'] ?? '')) ?></strong></p>
            <label class="field <?= isset($errors['password']) && $openModal === 'password' ? 'has-error' : '' ?>">
                <span>Nova senha</span>
                <input type="password" name="password" minlength="8" required>
                <?= $openModal === 'password' ? $fieldError('password') : '' ?>
            </label>
            <label class="field <?= isset($errors['password_confirmation']) && $openModal === 'password' ? 'has-error' : '' ?>">
                <span>Confirmar senha</span>
                <input type="password" name="password_confirmation" minlength="8" required>
                <?= $openModal === 'password' ? $fieldError('password_confirmation') : '' ?>
            </label>
            <footer class="form-actions">
                <button class="btn btn-muted" type="button" data-user-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Redefinir</span></button>
            </footer>
        </form>
    </div>
</div>
