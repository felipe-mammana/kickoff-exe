<?php

declare(strict_types=1);

$source = file_get_contents(dirname(__DIR__) . '/public/assets/js/app.js');
if (!is_string($source)) throw new RuntimeException('Nao foi possivel ler app.js.');
$indexView = file_get_contents(dirname(__DIR__) . '/views/vault/index.php');
$showView = file_get_contents(dirname(__DIR__) . '/views/vault/show.php');
if (!is_string($indexView) || !is_string($showView)) {
    throw new RuntimeException('Nao foi possivel ler as views do cofre.');
}

$checks = [
    'Nao reutiliza dataset.loaded' => !str_contains($source, 'dataset.loaded'),
    'Nao persiste confirmacao no dataset' => !str_contains($source, 'vaultRevealConfirmed'),
    'Cancela requisicoes em andamento' => str_contains($source, 'new AbortController()') && str_contains($source, 'state.controller?.abort()'),
    'Descarta respostas atrasadas' => str_contains($source, 'state.requestId !== requestId'),
    'Expira revelacao em 15 segundos' => str_contains($source, '}, 15000)'),
    'Limpa ao ocultar e fechar modal' => str_contains($source, 'function clearVaultSecret(cell)') && str_contains($source, 'function closeVaultModals()'),
    'Limpa ao trocar de aba' => str_contains($source, "document.addEventListener('visibilitychange'") && str_contains($source, 'document.hidden'),
    'Limpa ao navegar' => str_contains($source, "window.addEventListener('pagehide'") && str_contains($source, "window.addEventListener('beforeunload'"),
    'Fetch sensivel desabilita cache' => str_contains($source, "cache: 'no-store'"),
    'Configuração de campos usa JSON válido no cadastro' => str_contains($indexView, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT')
        && str_contains($showView, 'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT')
        && !str_contains($indexView, 'e(json_encode($vaultCustomFieldConfig')
        && !str_contains($showView, 'e(json_encode($vaultCustomFieldConfig'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    echo ($passed ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$passed) $failed++;
}
exit($failed === 0 ? 0 : 1);
