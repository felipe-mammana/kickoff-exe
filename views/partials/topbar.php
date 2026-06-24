<header class="topbar">
    <div class="topbar-left">
        <button class="icon-btn sidebar-toggle" type="button" data-sidebar-toggle aria-label="Abrir menu">
            <?= icon('menu') ?>
        </button>
        <div class="topbar-search" role="search">
            <?= icon('search') ?>
            <input type="search" placeholder="Pesquisar dispositivos..." aria-label="Pesquisar dispositivos">
        </div>
    </div>

    <nav class="top-actions" aria-label="Acoes da conta">
        <button class="icon-btn theme-toggle" type="button" data-theme-toggle aria-label="Alternar tema" title="Alternar tema">
            <span class="theme-glyph" aria-hidden="true"></span>
        </button>
        <span class="user-chip"><?= e(current_user()['name']) ?></span>
        <form action="/?route=logout" method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-muted" type="submit"><?= icon('log-out') ?><span>Sair</span></button>
        </form>
    </nav>
</header>
