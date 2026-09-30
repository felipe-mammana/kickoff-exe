<section class="content-panel sessions-panel">
    <header class="sessions-heading"><div><h1>Sessões</h1><p><?= $online ?> online · <?= $total - $online ?> offline</p></div><a class="icon-btn" href="<?= e('/?route=sessions.index&' . http_build_query(['status' => $status, 'q' => $query])) ?>" title="Atualizar sessões" aria-label="Atualizar sessões"><?= icon('refresh-cw') ?></a></header>
    <form class="sessions-filters" method="get">
        <input type="hidden" name="route" value="sessions.index">
        <label class="field"><span>Usuário ou IP</span><input type="search" name="q" value="<?= e($query) ?>"></label>
        <label class="field"><span>Status</span><select name="status"><?php foreach (['all' => 'Todos', 'online' => 'Online', 'offline' => 'Offline'] as $key => $label): ?><option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary" type="submit"><?= icon('search') ?>Filtrar</button>
    </form>
    <div class="sessions-table-scroll" tabindex="0" role="region" aria-label="Sessões dos usuários">
        <table class="sessions-table">
            <thead><tr><th scope="col">Usuário</th><th scope="col">IP</th><th scope="col">Navegador</th><th scope="col">Última atividade</th><th scope="col">Última ação registrada</th><th scope="col" style="width:64px">Ações</th></tr></thead>
            <tbody>
        <?php foreach ($sessions as $item): ?>
            <tr>
                <td><div class="session-identity"><span class="presence-dot <?= $item['online'] ? 'is-online' : 'is-offline' ?>" role="img" aria-label="<?= $item['online'] ? 'Online' : 'Offline' ?>" title="<?= $item['online'] ? 'Atividade nos últimos dois minutos' : 'Offline' ?>"></span><div><strong><?= e($item['name']) ?></strong><small><?= e($item['email']) ?></small><?php if (!$item['is_active']): ?><small>Conta desativada</small><?php endif; ?></div></div></td>
                <td><?= e($item['ip_address'] ?? 'Não registrado') ?></td>
                <td><?= e(SessionPresence::browser((string) ($item['user_agent'] ?? ''))) ?></td>
                <td><?= e($item['last_seen_at'] ?? 'Sem atividade registrada') ?></td>
                <td><div class="session-action"><span><?= e($item['last_action'] ?? 'Nenhuma ação') ?></span><small><?= e($item['last_action_at'] ?? '') ?></small><?php if (can_permission('audit.view')): ?><a href="/?route=audit.index&amp;user_id=<?= (int) $item['id'] ?>"><?= icon('file-clock') ?>Ver auditoria</a><?php endif; ?></div></td>
                <td><?php if ($item['has_session'] && can_permission('sessions.terminate')): ?><form method="post" action="/?route=sessions.terminate"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="user_id" value="<?= (int) $item['id'] ?>"><button class="icon-btn" type="submit" title="Encerrar sessão" aria-label="Encerrar sessão de <?= e($item['name']) ?>"><?= icon('log-out') ?></button></form><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$sessions): ?><tr><td colspan="6">Nenhuma sessão encontrada.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
