<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . safe_redirect_path($path));
    exit;
}

function safe_redirect_path(string $path): string
{
    $path = trim($path);

    if ($path === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path) || preg_match('/[\r\n]/', $path)) {
        return '/';
    }

    return $path[0] === '/' ? $path : '/' . $path;
}

function current_user(): ?array
{
    if (empty($_SESSION['user']['id'])) {
        return null;
    }

    static $checkedUserId = null;
    static $checkedSessionToken = null;

    $sessionToken = (string) ($_SESSION['session_token'] ?? '');

    if ($checkedUserId !== (int) $_SESSION['user']['id'] || $checkedSessionToken !== $sessionToken) {
        $user = User::find((int) $_SESSION['user']['id']);
        if (!$user) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            return null;
        }

        $activeSessionToken = (string) ($user['active_session_token'] ?? '');
        if (empty($user['is_active']) || $sessionToken === '' || !hash_equals($activeSessionToken, $sessionToken)) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            return null;
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'is_admin' => (int) ($user['is_admin'] ?? 0),
            'two_factor_enabled' => (int) ($user['two_factor_enabled'] ?? 0),
            'preferred_theme' => $user['preferred_theme'] ?? 'light',
            'sidebar_default' => $user['sidebar_default'] ?? 'expanded',
            'table_page_size' => (int) ($user['table_page_size'] ?? 25),
            'datetime_format' => $user['datetime_format'] ?? 'd/m/Y H:i',
        ];
        $checkedUserId = (int) $user['id'];
        $checkedSessionToken = $sessionToken;
    }

    return $_SESSION['user'];
}

function require_auth(): void
{
    if (!current_user()) {
        redirect('/?route=login');
    }
}

function is_admin(): bool
{
    return !empty(current_user()['is_admin']);
}

function require_admin(): void
{
    require_auth();

    if (!is_admin()) {
        http_response_code(403);
        view('errors/403', ['title' => 'Acesso negado']);
        exit;
    }
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }

    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }

    return parse_url(APP_URL, PHP_URL_SCHEME) === 'https';
}

function csp_nonce(): string
{
    static $nonce = null;

    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
    }

    return $nonce;
}

function apply_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    $nonce = csp_nonce();
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "connect-src 'self'",
        "font-src 'self' data:",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "img-src 'self' data: blob:",
        "object-src 'none'",
        "script-src 'self' 'nonce-" . $nonce . "'",
        "style-src 'self'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $directives));
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Permissions-Policy: camera=(self), microphone=(), geolocation=(), payment=(), usb=()');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !csrf_token_is_valid($token)) {
        http_response_code(419);
        exit('Sessão expirada. Volte e tente novamente.');
    }
}

function csrf_token_is_valid(?string $token): bool
{
    return is_string($token)
        && $token !== ''
        && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function consume_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function view(string $view, array $data = []): void
{
    if (!preg_match('#^[a-zA-Z0-9_/-]+$#', $view) || strpos($view, '..') !== false) {
        throw new InvalidArgumentException('View inválida.');
    }

    extract($data, EXTR_SKIP);
    $viewFile = BASE_PATH . '/views/' . $view . '.php';

    if (!is_file($viewFile)) {
        throw new RuntimeException('View não encontrada.');
    }

    require BASE_PATH . '/views/layout.php';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function pretty_json(?string $json): string
{
    if (!$json) {
        return '-';
    }

    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $json;
    }

    return (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function brand_logo(string $class = 'brand-logo', bool $markOnly = false): string
{
    $src = $markOnly ? '/assets/brand/exe-icon.webp' : '/assets/brand/exe-logo.webp';
    $alt = $markOnly ? 'EXE' : 'EXE Solucoes Estrategicas em TI';

    return '<img class="' . e($class) . '" src="' . e($src) . '" alt="' . e($alt) . '">';
}

function export_url(string $type, string $format, array $filters = []): string
{
    $params = array_filter(array_merge([
        'route' => 'export.download',
        'type' => $type,
        'format' => $format,
    ], $filters), static fn ($value): bool => (string) $value !== '');

    return '/?' . http_build_query($params);
}

function safe_download_filename(string $filename): string
{
    $filename = basename(str_replace(["\r", "\n", '"', '\\'], '', $filename));
    $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'download';

    return trim($filename, '._') ?: 'download';
}

function format_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }

    return $bytes . ' B';
}

function safe_external_url(?string $url): ?string
{
    $url = trim((string) $url);

    if ($url === '') {
        return null;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

function upload_file_path(string $fileName): ?string
{
    $fileName = trim($fileName);

    if ($fileName === '' || basename($fileName) !== $fileName || preg_match('/[\\\\\/\x00-\x1F\x7F]/', $fileName)) {
        return null;
    }

    $roots = [UPLOAD_PATH];
    if (defined('LEGACY_UPLOAD_PATH')) {
        $roots[] = LEGACY_UPLOAD_PATH;
    }

    foreach ($roots as $root) {
        $uploadRoot = realpath((string) $root);
        if ($uploadRoot === false) {
            continue;
        }

        $path = $uploadRoot . DIRECTORY_SEPARATOR . $fileName;
        $directory = realpath(dirname($path));
        if ($directory === false || $directory !== $uploadRoot || !is_file($path)) {
            continue;
        }

        return $path;
    }

    $primaryRoot = realpath(UPLOAD_PATH);
    return $primaryRoot === false ? null : $primaryRoot . DIRECTORY_SEPARATOR . $fileName;
}

function upload_file_url(string $fileName): string
{
    $safeName = upload_file_path($fileName) !== null ? $fileName : '';

    return '/?route=machines.photos.view&file=' . rawurlencode($safeName);
}

function safe_original_filename(string $fileName): string
{
    $fileName = basename(str_replace(["\r", "\n", "\0"], '', $fileName));
    $fileName = preg_replace('/[^\pL\pN._ -]+/u', '_', $fileName) ?: 'arquivo';

    $fileName = trim($fileName);
    $fileName = function_exists('mb_substr') ? mb_substr($fileName, 0, 180, 'UTF-8') : substr($fileName, 0, 180);

    return $fileName ?: 'arquivo';
}

function icon(string $name, string $class = 'icon'): string
{
    $icons = [
        'layout-dashboard' => '<rect width="7" height="9" x="3" y="3" rx="1"></rect><rect width="7" height="5" x="14" y="3" rx="1"></rect><rect width="7" height="9" x="14" y="12" rx="1"></rect><rect width="7" height="5" x="3" y="16" rx="1"></rect>',
        'building-2' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18"></path><path d="M6 12H4a2 2 0 0 0-2 2v8"></path><path d="M18 9h2a2 2 0 0 1 2 2v11"></path><path d="M10 6h4"></path><path d="M10 10h4"></path><path d="M10 14h4"></path><path d="M10 18h4"></path>',
        'monitor-cog' => '<path d="M12 17v4"></path><path d="M8 21h8"></path><rect width="20" height="14" x="2" y="3" rx="2"></rect><path d="m19.5 11.5.6.4"></path><path d="m15.9 9.1.6.4"></path><path d="m15.9 13.9.6-.4"></path><path d="m19.5 9.5.6-.4"></path><circle cx="18" cy="11.5" r="1.7"></circle>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'file-clock' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h7"></path><path d="M14 2v6h6"></path><path d="M16 14v3l2 1"></path><circle cx="17" cy="17" r="5"></circle>',
        'settings' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle>',
        'lock' => '<rect width="18" height="11" x="3" y="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path>',
        'folder' => '<path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9L9.6 4A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"></path>',
        'folder-plus' => '<path d="M12 10v6"></path><path d="M9 13h6"></path><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.7-.9L9.6 4A2 2 0 0 0 7.9 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2z"></path>',
        'key-round' => '<path d="M2 18v3h3l9.2-9.2"></path><circle cx="15" cy="9" r="6"></circle><path d="m18.5 5.5-2 2"></path>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'shield' => '<path d="M20 13c0 5-3.5 7.5-7.2 8.8a2.3 2.3 0 0 1-1.6 0C7.5 20.5 4 18 4 13V5.8a2 2 0 0 1 1.2-1.8l6-2.6a2 2 0 0 1 1.6 0l6 2.6A2 2 0 0 1 20 5.8z"></path>',
        'cloud' => '<path d="M17.5 19H9a7 7 0 1 1 6.7-9h1.8a4.5 4.5 0 1 1 0 9z"></path>',
        'globe' => '<circle cx="12" cy="12" r="10"></circle><path d="M2 12h20"></path><path d="M12 2a15.3 15.3 0 0 1 0 20"></path><path d="M12 2a15.3 15.3 0 0 0 0 20"></path>',
        'server' => '<rect width="20" height="8" x="2" y="2" rx="2"></rect><rect width="20" height="8" x="2" y="14" rx="2"></rect><path d="M6 6h.01"></path><path d="M6 18h.01"></path>',
        'wifi' => '<path d="M5 13a10 10 0 0 1 14 0"></path><path d="M8.5 16.5a5 5 0 0 1 7 0"></path><path d="M2 9a15 15 0 0 1 20 0"></path><path d="M12 20h.01"></path>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"></path><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"></path>',
        'mail' => '<rect width="20" height="16" x="2" y="4" rx="2"></rect><path d="m22 7-8.97 5.7a2 2 0 0 1-2.06 0L2 7"></path>',
        'message-circle' => '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22z"></path>',
        'phone' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8 9.7a16 16 0 0 0 6.3 6.3l1.3-1.3a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7A2 2 0 0 1 22 16.9z"></path>',
        'credit-card' => '<rect width="20" height="14" x="2" y="5" rx="2"></rect><path d="M2 10h20"></path>',
        'landmark' => '<path d="M3 22h18"></path><path d="M6 18v-7"></path><path d="M10 18v-7"></path><path d="M14 18v-7"></path><path d="M18 18v-7"></path><path d="m12 2 9 5H3z"></path>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="m16 17 5-5-5-5"></path><path d="M21 12H9"></path>',
        'plus' => '<path d="M5 12h14"></path><path d="M12 5v14"></path>',
        'save' => '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8A2 2 0 0 1 21 8.8V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path>',
        'edit-3' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path>',
        'trash-2' => '<path d="M3 6h18"></path><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"></path><circle cx="12" cy="12" r="3"></circle>',
        'copy' => '<rect width="14" height="14" x="8" y="8" rx="2"></rect><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"></path>',
        'image' => '<rect width="18" height="18" x="3" y="3" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"></path>',
        'camera' => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"></path><circle cx="12" cy="13" r="3"></circle>',
        'menu' => '<path d="M4 12h16"></path><path d="M4 6h16"></path><path d="M4 18h16"></path>',
        'filter' => '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"></path>',
        'search' => '<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>',
        'laptop' => '<path d="M20 16V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v10"></path><path d="M2 20h20"></path><path d="M6 20a2 2 0 0 1-2-2v-2h16v2a2 2 0 0 1-2 2"></path>',
        'monitor' => '<rect width="20" height="14" x="2" y="3" rx="2"></rect><path d="M8 21h8"></path><path d="M12 17v4"></path>',
        'smartphone' => '<rect width="14" height="20" x="5" y="2" rx="2"></rect><path d="M12 18h.01"></path>',
        'printer' => '<path d="M6 9V2h12v7"></path><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path>',
        'router' => '<rect width="20" height="8" x="2" y="14" rx="2"></rect><path d="M6.01 18H6"></path><path d="M10.01 18H10"></path><path d="M15 10v4"></path><path d="M17.84 7.17a4 4 0 0 0-5.66 0"></path><path d="M20.66 4.34a8 8 0 0 0-11.32 0"></path>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path>',
        'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m17 8-5-5-5 5"></path><path d="M12 3v12"></path>',
        'more-vertical' => '<circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle>',
        'arrow-left' => '<path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"></path>',
        'chevron-left' => '<path d="m15 18-6-6 6-6"></path>',
        'x' => '<path d="M18 6 6 18"></path><path d="m6 6 12 12"></path>',
        'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"></path><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"></circle>',
        'file-check' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="m9 15 2 2 4-4"></path>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path>',
        'file-spreadsheet' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M8 13h8"></path><path d="M8 17h8"></path><path d="M10 9H8"></path><path d="M12 13v4"></path>',
        'briefcase' => '<rect width="20" height="14" x="2" y="7" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
        'terminal' => '<path d="m4 17 6-6-6-6"></path><path d="M12 19h8"></path>',
        'code' => '<path d="m16 18 6-6-6-6"></path><path d="m8 6-6 6 6 6"></path>',
        'link' => '<path d="M10 13a5 5 0 0 0 7.1 0l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.1 0l-2 2a5 5 0 0 0 7.1 7.1l1.1-1.1"></path>',
        'star' => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8-6.2-3.3L5.8 21 7 14.2 2 9.3l6.9-1z"></path>',
        'braces' => '<path d="M8 3H7a2 2 0 0 0-2 2v4a2 2 0 0 1-2 2 2 2 0 0 1 2 2v4a2 2 0 0 0 2 2h1"></path><path d="M16 21h1a2 2 0 0 0 2-2v-4a2 2 0 0 1 2-2 2 2 0 0 1-2-2V7a2 2 0 0 0-2-2h-1"></path>',
        'check-circle' => '<path d="M9 12l2 2 4-4"></path><circle cx="12" cy="12" r="10"></circle>',
        'circle' => '<circle cx="12" cy="12" r="10"></circle>',
        'warning' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
        'history' => '<path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 3v6h6"></path><path d="M12 7v5l4 2"></path>',
    ];

    $body = $icons[$name] ?? $icons['settings'];

    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
