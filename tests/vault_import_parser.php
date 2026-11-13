<?php

declare(strict_types=1);

$standalone = !function_exists('check');
if ($standalone) {
    $passed = 0;
    $failed = 0;
    function check(string $label, bool $condition): void
    {
        global $passed, $failed;
        if ($condition) {
            $passed++;
            echo "[OK] {$label}\n";
        } else {
            $failed++;
            echo "[ERRO] {$label}\n";
        }
    }
    defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
    defined('APP_KEY') || define('APP_KEY', 'test-only-vault-import-key-32-bytes-minimum');
    require_once BASE_PATH . '/includes/SimpleZipWriter.php';
    require_once BASE_PATH . '/includes/SafeZipReader.php';
    require_once BASE_PATH . '/includes/VaultImportParser.php';
    require_once BASE_PATH . '/includes/VersionedCrypto.php';
    require_once BASE_PATH . '/includes/CredentialCrypto.php';
    require_once BASE_PATH . '/includes/VaultImportPreview.php';
}

$temporary = [];
$makeTemp = static function (string $suffix, string $contents) use (&$temporary): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kickoff-import-' . bin2hex(random_bytes(6)) . $suffix;
    file_put_contents($path, $contents);
    $temporary[] = $path;
    return $path;
};

try {
    $csv = "Equipamento;Usuário;Senha;IP interno;MAC;SSID;URL;Observações\n"
        . "Roteador matriz;admin.ficticio;Segredo-Teste-1;192.0.2.10;02:00:5E:10:00:01;REDE-TESTE;https://router.example.test;Somente fixture\n"
        . "Servidor teste;svc.ficticio;Segredo-Teste-2;198.51.100.20;;;https://srv.example.test;Conta automatizada\n";
    $csvPath = $makeTemp('.csv', $csv);
    $csvResult = VaultImportParser::parseFile($csvPath, 'credenciais.csv', 'text/csv');
    check('Importação CSV reconhece múltiplas credenciais', count($csvResult['records']) === 2);
    check('Importação CSV mapeia campos de rede', ($csvResult['records'][0]['ip_internal'] ?? '') === '192.0.2.10'
        && ($csvResult['records'][0]['ssid'] ?? '') === 'REDE-TESTE');
    $csvBlocksPath = $makeTemp('.csv', "Nome;Usuário;Senha\nBloco A;conta.a;Segredo-Bloco-A\nNome;Usuário;Senha\nBloco B;conta.b;Segredo-Bloco-B\n");
    $csvBlocksResult = VaultImportParser::parseFile($csvBlocksPath, 'blocos.csv', 'text/csv');
    check('Importação CSV aceita blocos com cabeçalhos repetidos', count($csvBlocksResult['records']) === 2);

    $xlsx = new SimpleZipWriter();
    $xlsx->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $xlsx->addFile('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Acessos" sheetId="1"/></sheets></workbook>');
    $xlsx->addFile('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
        . '<row r="1"><c r="A1" t="inlineStr"><is><t>Nome do serviço</t></is></c><c r="B1" t="inlineStr"><is><t>Login</t></is></c><c r="C1" t="inlineStr"><is><t>Segredo</t></is></c><c r="D1" t="inlineStr"><is><t>IP externo</t></is></c></row>'
        . '<row r="2"><c r="A2" t="inlineStr"><is><t>VPN fictícia</t></is></c><c r="B2" t="inlineStr"><is><t>vpn.teste</t></is></c><c r="C2" t="inlineStr"><is><t>Segredo-XLSX-1</t></is></c><c r="D2" t="inlineStr"><is><t>203.0.113.40</t></is></c></row>'
        . '</sheetData></worksheet>');
    $xlsxPath = $makeTemp('.xlsx', $xlsx->output());
    $xlsxResult = VaultImportParser::parseFile($xlsxPath, 'credenciais.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    check('Importação XLSX lê linhas sem executar fórmulas', count($xlsxResult['records']) === 1
        && ($xlsxResult['records'][0]['username'] ?? '') === 'vpn.teste');
    check('Importação XLSX mapeia IP externo', ($xlsxResult['records'][0]['ip_external'] ?? '') === '203.0.113.40');

    $docx = new SimpleZipWriter();
    $docx->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $docx->addFile('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        . '<w:p><w:r><w:t>Roteadores de teste</w:t></w:r></w:p>'
        . '<w:tbl>'
        . '<w:tr><w:tc><w:p><w:r><w:t>Equipamento</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Administrador</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Senha</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>IP de acesso</w:t></w:r></w:p></w:tc></w:tr>'
        . '<w:tr><w:tc><w:p><w:r><w:t>Router laboratório</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>admin.lab</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Segredo-DOCX-1</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>192.0.2.50</w:t></w:r></w:p></w:tc></w:tr>'
        . '<w:tr><w:tc><w:p><w:r><w:t>Switch laboratório</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>admin.switch</w:t><w:br/><w:t>suporte.switch</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Segredo-DOCX-3</w:t><w:br/><w:t>Segredo-DOCX-4</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>192.0.2.60</w:t></w:r></w:p></w:tc></w:tr>'
        . '</w:tbl>'
        . '<w:p><w:r><w:t>Nome: Serviço livre</w:t></w:r></w:p><w:p><w:r><w:t>Usuário: livre.teste</w:t></w:r></w:p><w:p><w:r><w:t>Senha: Segredo-DOCX-2</w:t></w:r></w:p>'
        . '</w:body></w:document>');
    $docxPath = $makeTemp('.docx', $docx->output());
    $docxResult = VaultImportParser::parseFile($docxPath, 'credenciais.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    check('Importação DOCX interpreta tabela e bloco de texto', count($docxResult['records']) === 4);
    check('Importação DOCX preserva contexto para categoria', str_contains((string) ($docxResult['records'][0]['category_hint'] ?? ''), 'Roteadores'));
    check('Importação DOCX separa múltiplas contas do mesmo equipamento', ($docxResult['records'][1]['title'] ?? '') === 'Switch laboratório'
        && ($docxResult['records'][2]['title'] ?? '') === 'Switch laboratório'
        && ($docxResult['records'][1]['username'] ?? '') !== ($docxResult['records'][2]['username'] ?? ''));

    $linkedDocx = new SimpleZipWriter();
    $linkedDocx->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $linkedDocx->addFile('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://admin.example.test:8443/painel?origem=docx" TargetMode="External"/></Relationships>');
    $linkedDocx->addFile('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Equipamento linkado</w:t></w:r></w:p><w:p><w:r><w:t>Usuário: admin.link</w:t></w:r></w:p><w:p><w:r><w:t>Senha: Segredo-Link-1</w:t></w:r></w:p><w:p><w:hyperlink r:id="rId1"><w:r><w:t>Painel administrativo</w:t></w:r></w:hyperlink></w:p></w:body></w:document>');
    $linkedDocxPath = $makeTemp('.docx', $linkedDocx->output());
    $linkedDocxResult = VaultImportParser::parseFile($linkedDocxPath, 'link.docx', 'application/zip');
    check('DOCX aceita hyperlink HTTPS como texto', ($linkedDocxResult['records'][0]['service_url'] ?? '') === 'https://admin.example.test:8443/painel?origem=docx');

    $linkedXlsx = new SimpleZipWriter();
    $linkedXlsx->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $linkedXlsx->addFile('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheets><sheet name="Acessos" sheetId="1"/></sheets></workbook>');
    $linkedXlsx->addFile('xl/worksheets/_rels/sheet1.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="http://192.0.2.44:8080/admin/login" TargetMode="External"/></Relationships>');
    $linkedXlsx->addFile('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Nome</t></is></c><c r="B1" t="inlineStr"><is><t>Usuário</t></is></c><c r="C1" t="inlineStr"><is><t>Senha</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>VPN administrativa</t></is></c><c r="B2" t="inlineStr"><is><t>vpn.admin</t></is></c><c r="C2" t="inlineStr"><is><t>Segredo-Link-2</t></is></c></row></sheetData><hyperlinks><hyperlink ref="A2" r:id="rId1"/></hyperlinks></worksheet>');
    $linkedXlsxPath = $makeTemp('.xlsx', $linkedXlsx->output());
    $linkedXlsxResult = VaultImportParser::parseFile($linkedXlsxPath, 'link.xlsx', 'application/zip');
    check('XLSX aceita hyperlink HTTP com IP e porta', ($linkedXlsxResult['records'][0]['service_url'] ?? '') === 'http://192.0.2.44:8080/admin/login');

    $multipleLinks = new SimpleZipWriter();
    $multipleLinks->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $multipleLinks->addFile('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://admin.example.test/um" TargetMode="External"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="http://192.0.2.55:8081/dois" TargetMode="External"/></Relationships>');
    $multipleLinks->addFile('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body><w:p><w:r><w:t>Senha: Segredo-Link-5</w:t></w:r></w:p><w:p><w:hyperlink r:id="rId1"><w:r><w:t>Painel 1</w:t></w:r></w:hyperlink><w:hyperlink r:id="rId2"><w:r><w:t>Painel 2</w:t></w:r></w:hyperlink></w:p></w:body></w:document>');
    $multipleLinksPath = $makeTemp('.docx', $multipleLinks->output());
    $multipleLinksResult = VaultImportParser::parseFile($multipleLinksPath, 'varios-links.docx', 'application/zip');
    check('Múltiplos hyperlinks são preservados para revisão manual', ($multipleLinksResult['records'][0]['service_url'] ?? '') === ''
        && str_contains((string) ($multipleLinksResult['records'][0]['notes'] ?? ''), 'https://admin.example.test/um')
        && str_contains((string) ($multipleLinksResult['records'][0]['notes'] ?? ''), 'http://192.0.2.55:8081/dois'));

    $invalidLink = new SimpleZipWriter();
    $invalidLink->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $invalidLink->addFile('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/></Relationships>');
    $invalidLink->addFile('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Senha: Segredo-Link-3</w:t></w:r></w:p></w:body></w:document>');
    $invalidLinkPath = $makeTemp('.docx', $invalidLink->output());
    $invalidRejected = false;
    try {
        VaultImportParser::parseFile($invalidLinkPath, 'invalido.docx', 'application/zip');
    } catch (InvalidArgumentException $error) {
        $invalidRejected = str_contains($error->getMessage(), 'relacionamento externo');
    }
    check('Hyperlink com esquema malicioso continua rejeitado', $invalidRejected);

    $forbiddenRelation = new SimpleZipWriter();
    $forbiddenRelation->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $forbiddenRelation->addFile('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="https://evil.example.test/template.dotm" TargetMode="External"/></Relationships>');
    $forbiddenRelation->addFile('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Senha: Segredo-Link-4</w:t></w:r></w:p></w:body></w:document>');
    $forbiddenRelationPath = $makeTemp('.docx', $forbiddenRelation->output());
    $forbiddenRejected = false;
    try {
        VaultImportParser::parseFile($forbiddenRelationPath, 'template.docx', 'application/zip');
    } catch (InvalidArgumentException $error) {
        $forbiddenRejected = str_contains($error->getMessage(), 'relacionamento externo');
    }
    check('Relacionamento externo não-hyperlink continua bloqueado', $forbiddenRejected);

    $dangerous = new SimpleZipWriter();
    $dangerous->addFile('[Content_Types].xml', '<?xml version="1.0"?><Types><Override ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
    $dangerous->addFile('word/document.xml', '<document/>');
    $dangerous->addFile('word/vbaProject.bin', 'fixture-only');
    $dangerousPath = $makeTemp('.docx', $dangerous->output());
    $rejected = false;
    try {
        VaultImportParser::parseFile($dangerousPath, 'ativo.docx', 'application/zip');
    } catch (InvalidArgumentException $error) {
        $rejected = str_contains($error->getMessage(), 'conteúdo ativo');
    }
    check('Importação rejeita conteúdo ativo em pacote Office', $rejected);

    $_SESSION = [];
    $cryptoFixture = 'Segredo-Crypto-Ficticio';
    $encryptedFixture = CredentialCrypto::encrypt($cryptoFixture, 'vault_import_preview');
    check('CredentialCrypto mantém envelope AES-GCM esperado', str_starts_with((string) $encryptedFixture, 'enc:v1:')
        && CredentialCrypto::decrypt($encryptedFixture, 'vault_import_preview') === $cryptoFixture);
    $token = VaultImportPreview::store([['title' => 'Fixture', 'secret_value' => 'Segredo-Preview-Ficticio']], 42);
    $sessionPayload = (string) ($_SESSION['vault_import_preview'] ?? '');
    $loadedPreview = VaultImportPreview::load($token, 42);
    check('Prévia guarda segredos cifrados na sessão', $sessionPayload !== '' && !str_contains($sessionPayload, 'Segredo-Preview-Ficticio'));
    check('Prévia cifrada pode ser recuperada pela mesma sessão', ($loadedPreview['records'][0]['title'] ?? '') === 'Fixture');
    check('Prévia rejeita usuário autenticado diferente', VaultImportPreview::load($token, 43) === null);
    $claimedPreview = VaultImportPreview::claim($token, 42);
    $busy = false;
    try {
        VaultImportPreview::claim($token, 42);
    } catch (VaultImportPreviewBusyException) {
        $busy = true;
    }
    check('Prévia impede confirmação concorrente', $busy && ($claimedPreview['state'] ?? '') === 'confirming');
    VaultImportPreview::clear();
    check('Cancelamento remove a prévia da sessão', !isset($_SESSION['vault_import_preview']));

    $_SESSION['vault_import_preview'] = 'enc:v1:conteudo-invalido';
    check('Prévia adulterada é tratada como inválida sem expor segredo', VaultImportPreview::load(null, 42) === null);
    VaultImportPreview::clear();
} finally {
    foreach ($temporary as $path) {
        if (is_file($path)) unlink($path);
    }
}

if ($standalone) {
    echo "\nResultado: {$passed} OK, {$failed} erro(s).\n";
    exit($failed > 0 ? 1 : 0);
}
