<?php
$errors = $errors ?? [];
$old = $old ?? [];
$openModal = $openModal ?? '';
$adminCount = 0;
$editorCount = 0;
$activeCount = 0;

foreach ($users as $user) {
    $role = User::roleFromUser($user);
    if ($role === 'admin') {
        $adminCount++;
    } elseif ($role === 'editor') {
        $editorCount++;
    }
    if (!empty($user['is_active'])) {
        $activeCount++;
    }
}

$viewerCount = count($users) - $adminCount - $editorCount;
$roleIcons = ['admin' => 'settings', 'editor' => 'edit-3', 'viewer' => 'users'];
$roleClasses = ['admin' => 'info', 'editor' => 'success', 'viewer' => 'neutral'];
$roleDescriptions = [
    'admin' => 'Permissões administrativas iniciais.',
    'editor' => 'Criação e edição como padrão.',
    'viewer' => 'Consulta e relatórios como padrão.',
];
$fieldError = static fn (string $field): string => isset($errors[$field]) ? '<small>' . e($errors[$field]) . '</small>' : '';
$oldValue = static fn (string $field, string $default = ''): string => e((string) ($old[$field] ?? $default));
$roleOptions = static function (string $selectedRole, string $dataAttribute = '') use ($roleIcons, $roleDescriptions): void {
    foreach (User::ROLES as $roleKey => $roleLabel):
        $data = $dataAttribute !== '' ? ' ' . $dataAttribute : '';
?>
        <label class="role-option-card <?= $selectedRole === $roleKey ? 'is-selected' : '' ?>">
            <input type="radio" name="role" value="<?= e($roleKey) ?>" <?= $selectedRole === $roleKey ? 'checked' : '' ?><?= $data ?> <?= !can_permission('users.permissions') ? 'disabled' : '' ?>>
            <span class="role-option-icon"><?= icon($roleIcons[$roleKey] ?? 'users') ?></span>
            <span>
                <strong><?= e($roleLabel) ?></strong>
                <small><?= e($roleDescriptions[$roleKey] ?? '') ?></small>
            </span>
        </label>
<?php
    endforeach;
};
$permissionOptions = static function (array $selected): void {
    $ceiling = UserPermission::delegation(current_user());
    $presets = [];
    foreach (array_keys(User::ROLES) as $role) $presets[$role] = array_values(array_intersect(UserPermission::defaults($role), $ceiling));
    ?>
    <fieldset class="user-permissions" data-user-permissions data-presets="<?= e(json_encode($presets)) ?>">
        <legend>Permissoes especificas</legend>
        <input type="hidden" name="permissions_present" value="1">
        <?php foreach (UserPermission::catalog() as $group => $area): ?>
            <fieldset class="permission-area">
                <legend><?= e($area['label']) ?></legend>
                <div class="permission-options">
                <?php foreach ($area['actions'] as $action => $label): $key = $group . '.' . $action; ?>
                    <label><input type="checkbox" name="permissions[]" value="<?= e($key) ?>"
                        <?= in_array($key, $selected, true) ? 'checked' : '' ?>
                        <?= !can_permission('users.permissions') || !in_array($key, $ceiling, true) ? 'disabled' : '' ?>>
                        <span><?= e($label) ?></span></label>
                <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endforeach; ?>
    </fieldset>
    <?php
};
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
        <?php if (can_permission('audit.view')): ?><a class="btn btn-muted" href="/?route=audit.index"><?= icon('file-clock') ?><span>Ver logs</span></a><?php endif; ?>
        <?php if (can_permission('users.create') && can_permission('users.permissions')): ?>
        <button class="btn btn-primary" type="button" data-user-modal-open="create"><?= icon('plus') ?><span>Novo usuário</span></button>
        <?php endif; ?>
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
            <strong><?= $editorCount ?></strong>
            <span>editores</span>
            </div>
    </article>
    <article class="summary-card">
        <span class="summary-icon"><?= icon('eye') ?></span>
        <div>
            <strong><?= $viewerCount ?></strong>
            <span>usuários</span>
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
        <?php if (can_permission('reports.export')): ?><div class="export-actions" data-export-actions>
            <span class="status-chip neutral"><?= count($users) ?> contas</span>
            <a class="btn btn-muted export-btn <?= !$users ? 'disabled' : '' ?>" href="<?= e(export_url('users', 'csv')) ?>" data-export-link data-export-format="CSV" aria-disabled="<?= !$users ? 'true' : 'false' ?>">
                <?= icon('file-spreadsheet') ?><span>CSV</span>
            </a>
            <a class="btn btn-muted export-btn <?= !$users ? 'disabled' : '' ?>" href="<?= e(export_url('users', 'json')) ?>" data-export-link data-export-format="JSON" aria-disabled="<?= !$users ? 'true' : 'false' ?>">
                <?= icon('braces') ?><span>JSON</span>
            </a>
        </div><?php endif; ?>
    </header>

    <?php if (!$users): ?>
        <div class="empty-state compact audit-empty">
            <span class="empty-icon"><?= icon('users') ?></span>
            <h3>Nenhum usuário cadastrado</h3>
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
                        $role = User::roleFromUser($user);
                        $roleLabel = User::roleLabel($role);
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
                                <span class="audit-action-badge <?= e($roleClasses[$role] ?? 'neutral') ?>">
                                    <?= icon($roleIcons[$role] ?? 'users') ?>
                                    <?= e($roleLabel) ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="status-chip <?= $isActive ? 'success' : 'neutral' ?>"><?= icon($isActive ? 'check-circle' : 'warning') ?><span><?= $isActive ? 'Ativo' : 'Inativo' ?></span></span>
                            </td>
                            <td data-label="Ações">
                                <div class="row-actions">
                                    <?php if (can_permission('users.edit') || can_permission('users.permissions')): ?>
                                    <button
                                        class="icon-btn"
                                        type="button"
                                        data-user-modal-open="edit"
                                        data-user-id="<?= (int) $user['id'] ?>"
                                        data-user-name="<?= e($user['name']) ?>"
                                        data-user-email="<?= e($user['email']) ?>"
                                        data-user-role="<?= e($role) ?>"
                                        data-user-permissions="<?= e(json_encode(UserPermission::effective($user))) ?>"
                                        aria-label="Editar usuário"
                                        title="Editar usuário"
                                    ><?= icon('edit-3') ?></button>
                                    <?php endif; ?>
                                    <?php if (can_permission('users.edit')): ?>
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
                                        <button class="icon-btn" type="submit" aria-label="<?= $isActive ? 'Desativar usuário' : 'Ativar usuário' ?>" title="<?= $isActive ? 'Desativar usuário' : 'Ativar usuário' ?>" <?= $isSelf && $isActive ? 'disabled' : '' ?>>
                                            <?= icon($isActive ? 'trash-2' : 'check-circle') ?>
                                        </button>
                                    </form>
                                    <?php endif; ?>
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
    <div class="company-modal-dialog user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-create-title">
        <header class="asset-panel-head user-modal-head">
            <div>
                <span><?= icon('users') ?></span>
                <div>
                    <span class="eyebrow">Acesso ao sistema</span>
                    <h2 id="user-create-title">Novo usuário</h2>
                    <p>Cadastre os dados de acesso e defina o nível correto de permissão.</p>
                </div>
            </div>
            <button class="icon-btn" type="button" data-user-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form" action="/?route=users.store" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($openModal === 'create' && $errors): ?>
                <div class="user-form-error-summary" role="alert" data-user-form-errors>
                    <?= icon('alert-triangle') ?>
                    <div><strong>Não foi possível salvar</strong><span>Revise os campos destacados abaixo.</span></div>
                </div>
            <?php endif; ?>
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
            <fieldset class="user-role-field <?= isset($errors['role']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <legend>Nível de acesso</legend>
                <div class="role-option-grid">
                    <?php $roleOptions($openModal === 'create' ? User::normalizeRole((string) ($old['role'] ?? 'viewer')) : 'viewer'); ?>
                </div>
                <?= $openModal === 'create' ? $fieldError('role') : '' ?>
            </fieldset>
            <?php $permissionOptions($openModal === 'create' ? ($old['permissions'] ?? UserPermission::defaults('viewer')) : UserPermission::defaults('viewer')); ?>
            <?= $openModal === 'create' ? $fieldError('permissions') : '' ?>
            <fieldset class="user-password-fields">
                <legend>Senha de acesso</legend>
            <label class="field <?= isset($errors['password']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <span>Senha inicial</span>
                <input type="password" name="password" minlength="8" maxlength="72" pattern="(?=.*\p{Ll})(?=.*\p{Lu})(?=.*[0-9])(?=.*[^\p{L}\p{N}\s]).{8,}" title="Minimo 8 caracteres: maiuscula, minuscula, numero e especial." required><?php require BASE_PATH . '/views/partials/password-requirements.php'; ?>
                <?= $openModal === 'create' ? $fieldError('password') : '' ?>
            </label>
            <label class="field <?= isset($errors['password_confirmation']) && $openModal === 'create' ? 'has-error' : '' ?>">
                <span>Confirmar senha</span>
                <input type="password" name="password_confirmation" minlength="8" required>
                <?= $openModal === 'create' ? $fieldError('password_confirmation') : '' ?>
            </label>
            </fieldset>
            <footer class="form-actions">
                <button class="btn btn-muted" type="button" data-user-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><span>Salvar</span></button>
            </footer>
        </form>
    </div>
</div>

<div class="company-modal user-modal" data-user-modal="edit" <?= $openModal === 'edit' ? '' : 'hidden' ?>>
    <div class="company-modal-dialog user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-edit-title">
        <header class="asset-panel-head user-modal-head">
            <div>
                <span><?= icon('edit-3') ?></span>
                <div>
                    <span class="eyebrow">Acesso ao sistema</span>
                    <h2 id="user-edit-title">Editar usuário</h2>
                    <p>Atualize os dados da conta e revise o nível de permissão.</p>
                </div>
            </div>
            <button class="icon-btn" type="button" data-user-modal-close aria-label="Fechar"><?= icon('x') ?></button>
        </header>
        <form class="company-form modal-company-form" action="/?route=users.update" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= $openModal === 'edit' ? (int) ($old['id'] ?? 0) : '' ?>" data-user-edit-id>
            <?php if ($openModal === 'edit' && $errors): ?>
                <div class="user-form-error-summary" role="alert" data-user-form-errors>
                    <?= icon('alert-triangle') ?>
                    <div>
                        <strong>Não foi possível salvar</strong>
                        <span><?= e((string) reset($errors)) ?></span>
                    </div>
                </div>
            <?php endif; ?>
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
            <fieldset class="user-role-field <?= isset($errors['role']) && $openModal === 'edit' ? 'has-error' : '' ?>">
                <legend>Nível de acesso</legend>
                <div class="role-option-grid">
                    <?php $roleOptions($openModal === 'edit' ? User::normalizeRole((string) ($old['role'] ?? 'viewer')) : 'viewer', 'data-user-edit-role-option'); ?>
                </div>
                <?= $openModal === 'edit' ? $fieldError('role') : '' ?>
            </fieldset>
            <?php $permissionOptions($openModal === 'edit' ? ($old['permissions'] ?? []) : []); ?>
            <?= $openModal === 'edit' ? $fieldError('permissions') : '' ?>
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
                <input type="password" name="password" minlength="8" maxlength="72" pattern="(?=.*\p{Ll})(?=.*\p{Lu})(?=.*[0-9])(?=.*[^\p{L}\p{N}\s]).{8,}" title="Minimo 8 caracteres: maiuscula, minuscula, numero e especial." required><?php require BASE_PATH . '/views/partials/password-requirements.php'; ?>
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
