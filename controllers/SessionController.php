<?php
declare(strict_types=1);

class SessionController
{
    public static function terminate(): void
    {
        require_auth();
        if (!can_permission('sessions.terminate')) {
            http_response_code(403);
            view('errors/403', ['title' => 'Acesso negado']);
            return;
        }
        header('Cache-Control: no-store, private, max-age=0');
        if (!is_post()) { http_response_code(405); return; }
        verify_csrf();
        $hash = (string) config_value('SESSION_ADMIN_PASSWORD_HASH', '');
        if ($hash === '' || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown') {
            flash('danger', 'Configure SESSION_ADMIN_PASSWORD_HASH no local.php para encerrar sessoes.');
            redirect('/?route=sessions.index');
        }
        $error = '';
        if (!isset($_POST['confirm_termination'])) {
            $target = User::find((int) ($_POST['user_id'] ?? 0));
            if (!$target || empty($target['active_session_token'])) {
                flash('danger', 'Esta conta nao possui sessao ativa.');
                redirect('/?route=sessions.index');
            }
            $_SESSION['termination_target'] = ['id' => (int) $target['id'], 'name' => $target['name'],
                'session_hash' => hash('sha256', $target['active_session_token']), 'expires' => time() + 300];
        } else {
            $pending = $_SESSION['termination_target'] ?? null;
            if (!$pending || $pending['expires'] <= time()) {
                unset($_SESSION['termination_target']);
                flash('danger', 'Confirmacao expirada. Selecione a sessao novamente.');
                redirect('/?route=sessions.index');
            }
            $actor = (int) current_user()['id'];
            $limit = SecurityRateLimit::hit('session-admin-password', $actor, 5, 600);
            if (!$limit['allowed'] || !password_verify((string) ($_POST['admin_password'] ?? ''), $hash)) {
                AuditLog::record(['action_type' => 'session_termination_denied', 'description' => 'Encerramento de sessao recusado por senha ou limite.']);
                $error = 'Senha administrativa incorreta ou limite de tentativas atingido.';
            } else {
                unset($_SESSION['termination_target']);
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare('SELECT active_session_token FROM users WHERE id = ? FOR UPDATE');
                    $stmt->execute([$pending['id']]);
                    $token = (string) $stmt->fetchColumn();
                    if ($token === '' || !hash_equals($pending['session_hash'], hash('sha256', $token))) {
                        $pdo->rollBack();
                        flash('danger', 'A sessao mudou ou ja foi encerrada. Selecione-a novamente.');
                        redirect('/?route=sessions.index');
                    }
                    AuditLog::record(['action_type' => 'session_terminated', 'affected_table' => 'users',
                        'affected_record_id' => $pending['id'], 'description' => 'Sessao encerrada por operador autorizado.',
                        'new_data' => ['target_user_id' => $pending['id']], 'required' => true]);
                    User::clearActiveSession($pending['id'], $token);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    throw $e;
                }
                flash('success', 'Sessao encerrada. O acesso sera recusado na proxima requisicao.');
                redirect('/?route=sessions.index');
            }
        }
        view('sessions/terminate', ['title' => 'Encerrar sessao', 'targetName' => $_SESSION['termination_target']['name'], 'error' => $error]);
    }

    public static function index(): void
    {
        require_auth();
        if (!can_permission('sessions.view')) {
            http_response_code(403);
            view('errors/403', ['title' => 'Acesso negado']);
            return;
        }
        header('Cache-Control: no-store, private, max-age=0');
        $status = (string) ($_GET['status'] ?? 'all');
        $query = trim((string) ($_GET['q'] ?? ''));
        $sessions = SessionPresence::all();
        $online = count(array_filter($sessions, static fn ($row) => (int) $row['online'] === 1));
        $total = count($sessions);
        $sessions = array_values(array_filter($sessions, static function ($row) use ($status, $query) {
            if ($status === 'online' && !$row['online']) return false;
            if ($status === 'offline' && $row['online']) return false;
            return $query === '' || mb_stripos($row['name'] . ' ' . $row['email'] . ' ' . ($row['ip_address'] ?? ''), $query) !== false;
        }));
        view('sessions/index', ['title' => 'Sessoes', 'sessions' => $sessions, 'online' => $online, 'total' => $total, 'status' => $status, 'query' => $query]);
    }
}
