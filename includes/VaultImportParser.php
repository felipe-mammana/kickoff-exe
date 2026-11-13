<?php

declare(strict_types=1);

final class VaultImportParser
{
    public const MAX_FILE_BYTES = 8_388_608;
    public const MAX_RECORDS = 200;
    private const MAX_ROWS = 600;
    private const MAX_COLUMNS = 50;
    private const MAX_CELL_BYTES = 5000;

    /** @var array<string, string[]> */
    private const ALIASES = [
        'title' => ['nome', 'equipamento', 'nome equipamento', 'nome do equipamento', 'servico', 'nome servico', 'nome do servico', 'dispositivo', 'host', 'hostname', 'servidor', 'conta'],
        'username' => ['usuario', 'user', 'login', 'administrador', 'admin', 'usuario administrador'],
        'secret_value' => ['senha', 'password', 'pass', 'segredo', 'chave de acesso', 'credencial'],
        'ip_internal' => ['ip', 'endereco ip', 'ip interno', 'ip de acesso', 'ip local', 'lan'],
        'ip_external' => ['ip externo', 'ip publico', 'ip wan', 'wan'],
        'mac' => ['mac', 'endereco mac', 'mac address'],
        'ssid' => ['ssid', 'nome wifi', 'nome do wifi', 'rede wifi', 'wi fi'],
        'service_url' => ['url', 'site', 'endereco web', 'link', 'url de acesso'],
        'notes' => ['observacao', 'observacoes', 'nota', 'notas', 'descricao', 'informacoes'],
    ];

    /**
     * @return array{records:array<int,array<string,mixed>>,warnings:string[],format:string}
     */
    public static function parseFile(string $path, string $originalName, string $mime): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException('Arquivo de importação inválido.');
        }
        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('O arquivo deve ter no máximo 8 MB.');
        }

        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['docx', 'xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Formato não permitido. Use DOCX, XLSX ou CSV.');
        }
        self::assertMime($extension, strtolower(trim($mime)));

        if ($extension === 'csv') {
            $records = self::parseCsv($path);
        } else {
            $zip = new SafeZipReader($path);
            self::assertSafeOfficePackage($zip, $extension);
            $records = $extension === 'docx' ? self::parseDocx($zip) : self::parseXlsx($zip);
        }

        $warnings = [];
        if (!$records) {
            throw new InvalidArgumentException('Nenhuma credencial reconhecível foi encontrada no arquivo.');
        }
        if (count($records) > self::MAX_RECORDS) {
            $records = array_slice($records, 0, self::MAX_RECORDS);
            $warnings[] = 'O arquivo excedeu o limite de 200 credenciais; revise o conteúdo e importe em partes.';
        }

        foreach ($records as $index => &$record) {
            $record['source_index'] = $index;
            $record['selected'] = empty($record['warnings']) || !in_array('Senha não identificada.', $record['warnings'], true);
        }
        unset($record);

        return ['records' => $records, 'warnings' => $warnings, 'format' => strtoupper($extension)];
    }

    private static function assertMime(string $extension, string $mime): void
    {
        $allowed = [
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip', 'application/x-zip', 'application/octet-stream',
            ],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip', 'application/x-zip', 'application/octet-stream',
            ],
            'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
        ];
        if (!in_array($mime, $allowed[$extension], true)) {
            throw new InvalidArgumentException('O tipo real do arquivo não corresponde à extensão informada.');
        }
    }

    private static function assertSafeOfficePackage(SafeZipReader $zip, string $extension): void
    {
        foreach ($zip->names() as $name) {
            $lower = strtolower($name);
            if (str_contains($lower, 'vbaproject')
                || str_contains($lower, '/activex/')
                || str_contains($lower, '/embeddings/')
                || str_contains($lower, '/externallinks/')
                || str_ends_with($lower, '.bin')
            ) {
                throw new InvalidArgumentException('O arquivo contém conteúdo ativo ou incorporado e foi rejeitado.');
            }
            if (str_ends_with($lower, '.rels')) {
                $relationships = $zip->get($name, 1_048_576);
                if (preg_match('/TargetMode\s*=\s*["\']External["\']/i', $relationships)) {
                    throw new InvalidArgumentException('O arquivo contém vínculos externos e foi rejeitado.');
                }
            }
        }

        $contentTypes = $zip->get('[Content_Types].xml', 1_048_576);
        if ($extension === 'docx') {
            if (!$zip->has('word/document.xml')
                || !str_contains($contentTypes, 'wordprocessingml.document.main+xml')
            ) {
                throw new InvalidArgumentException('Estrutura DOCX inválida ou protegida.');
            }
        } elseif (!$zip->has('xl/workbook.xml')
            || !str_contains($contentTypes, 'spreadsheetml.sheet.main+xml')
        ) {
            throw new InvalidArgumentException('Estrutura XLSX inválida ou protegida.');
        }
    }

    /** @return array<int,array<string,mixed>> */
    private static function parseCsv(string $path): array
    {
        $raw = file_get_contents($path);
        if (!is_string($raw) || str_contains($raw, "\0")) {
            throw new InvalidArgumentException('CSV inválido ou em codificação não suportada.');
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            if (!is_string($converted) || !mb_check_encoding($converted, 'UTF-8')) {
                throw new InvalidArgumentException('O CSV deve usar UTF-8 ou Windows-1252.');
            }
            $raw = $converted;
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $sampleLine = strtok($raw, "\r\n") ?: '';
        $delimiter = self::detectDelimiter($sampleLine);

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('Não foi possível preparar o CSV.');
        }
        fwrite($stream, $raw);
        rewind($stream);
        $rows = [];
        while (($row = fgetcsv($stream, 0, $delimiter, '"', '\\')) !== false) {
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            $rows[] = self::sanitizeRow($row);
        }
        fclose($stream);

        return self::recordsFromRows($rows, 'CSV');
    }

    private static function detectDelimiter(string $line): string
    {
        $scores = [];
        foreach ([',', ';', "\t"] as $delimiter) {
            $scores[$delimiter] = count(str_getcsv($line, $delimiter, '"', '\\'));
        }
        arsort($scores);
        $delimiter = (string) array_key_first($scores);
        if (($scores[$delimiter] ?? 0) < 2) {
            throw new InvalidArgumentException('CSV sem colunas reconhecíveis.');
        }
        return $delimiter;
    }

    /** @return array<int,array<string,mixed>> */
    private static function parseDocx(SafeZipReader $zip): array
    {
        $document = self::loadXml($zip->get('word/document.xml', 8_388_608), 'DOCX');
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $body = $xpath->query('//w:body')->item(0);
        if (!$body instanceof DOMElement) {
            throw new InvalidArgumentException('Documento Word sem corpo legível.');
        }

        $records = [];
        $context = '';
        $block = [];
        foreach ($body->childNodes as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($node->localName === 'tbl') {
                $records = array_merge($records, self::recordsFromTextBlock($block, $context, 'DOCX texto'));
                $block = [];
                $rows = [];
                foreach ($xpath->query('./w:tr', $node) as $rowNode) {
                    $row = [];
                    foreach ($xpath->query('./w:tc', $rowNode) as $cellNode) {
                        $row[] = self::wordNodeText($xpath, $cellNode);
                    }
                    $rows[] = self::sanitizeRow($row);
                }
                $records = array_merge($records, self::recordsFromRows($rows, 'DOCX tabela', $context));
                continue;
            }
            if ($node->localName !== 'p') {
                continue;
            }

            $text = self::wordNodeText($xpath, $node);
            if ($text === '') {
                $records = array_merge($records, self::recordsFromTextBlock($block, $context, 'DOCX texto'));
                $block = [];
                continue;
            }
            if (self::containsRecognizedLabel($text) || str_contains($text, ':')) {
                $block[] = $text;
            } else {
                $records = array_merge($records, self::recordsFromTextBlock($block, $context, 'DOCX texto'));
                $block = [];
                if (mb_strlen($text) <= 160) {
                    $context = $text;
                }
            }
        }
        $records = array_merge($records, self::recordsFromTextBlock($block, $context, 'DOCX texto'));

        return self::deduplicateParsedRecords($records);
    }

    private static function wordNodeText(DOMXPath $xpath, DOMNode $node): string
    {
        $parts = [];
        foreach ($xpath->query('.//w:t|.//w:tab|.//w:br', $node) as $textNode) {
            if ($textNode->localName === 't') {
                $parts[] = $textNode->textContent;
            } else {
                $parts[] = "\n";
            }
        }
        return self::cleanCell(implode('', $parts));
    }

    /** @return array<int,array<string,mixed>> */
    private static function parseXlsx(SafeZipReader $zip): array
    {
        $sharedStrings = [];
        if ($zip->has('xl/sharedStrings.xml')) {
            $shared = self::loadXml($zip->get('xl/sharedStrings.xml', 6_291_456), 'XLSX');
            $xpath = new DOMXPath($shared);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xpath->query('//x:si') as $item) {
                $value = '';
                foreach ($xpath->query('.//x:t', $item) as $text) {
                    $value .= $text->textContent;
                }
                $sharedStrings[] = self::cleanCell($value);
            }
        }

        $sheetNames = array_values(array_filter(
            $zip->names(),
            static fn (string $name): bool => (bool) preg_match('#^xl/worksheets/sheet[0-9]+\.xml$#', $name)
        ));
        natsort($sheetNames);
        $sheetNames = array_slice(array_values($sheetNames), 0, 20);

        $records = [];
        $rowBudget = self::MAX_ROWS;
        foreach ($sheetNames as $sheetIndex => $sheetName) {
            if ($rowBudget <= 0) {
                break;
            }
            $sheet = self::loadXml($zip->get($sheetName, 8_388_608), 'XLSX');
            $xpath = new DOMXPath($sheet);
            $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = [];
            foreach ($xpath->query('//x:sheetData/x:row') as $rowNode) {
                if ($rowBudget-- <= 0) {
                    break;
                }
                $row = [];
                foreach ($xpath->query('./x:c', $rowNode) as $cellNode) {
                    if (!$cellNode instanceof DOMElement) {
                        continue;
                    }
                    $reference = (string) $cellNode->getAttribute('r');
                    $column = self::columnIndex($reference);
                    if ($column < 0 || $column >= self::MAX_COLUMNS) {
                        continue;
                    }
                    $type = (string) $cellNode->getAttribute('t');
                    $valueNode = $xpath->query('./x:v', $cellNode)->item(0);
                    $value = $valueNode ? $valueNode->textContent : '';
                    if ($type === 's') {
                        $value = $sharedStrings[(int) $value] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = '';
                        foreach ($xpath->query('.//x:is/x:t', $cellNode) as $text) {
                            $value .= $text->textContent;
                        }
                    }
                    $row[$column] = self::cleanCell($value);
                }
                if ($row) {
                    ksort($row);
                    $max = min(self::MAX_COLUMNS - 1, max(array_keys($row)));
                    $dense = [];
                    for ($column = 0; $column <= $max; $column++) {
                        $dense[] = $row[$column] ?? '';
                    }
                    $rows[] = $dense;
                }
            }
            $records = array_merge($records, self::recordsFromRows($rows, 'XLSX planilha ' . ($sheetIndex + 1)));
        }

        return self::deduplicateParsedRecords($records);
    }

    private static function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)[0-9]+$/i', $reference, $matches)) {
            return -1;
        }
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = $index * 26 + ord($letter) - 64;
        }
        return $index - 1;
    }

    /** @param array<int,array<int,string>> $rows @return array<int,array<string,mixed>> */
    private static function recordsFromRows(array $rows, string $source, string $context = ''): array
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => (bool) array_filter($row, static fn (string $value): bool => $value !== '')));
        if (!$rows) {
            return [];
        }

        $records = [];
        $mapping = null;
        $unmappedColumns = [];
        $foundHeader = false;
        foreach ($rows as $rowNumber => $row) {
            $candidate = [];
            foreach ($row as $column => $cell) {
                $key = self::canonicalHeaderKey($cell);
                if ($key !== null && !isset($candidate[$key])) {
                    $candidate[$key] = $column;
                }
            }
            if (count($candidate) >= 2 && (isset($candidate['secret_value']) || isset($candidate['username']))) {
                $mapping = $candidate;
                $foundHeader = true;
                $recognizedColumns = array_flip(array_values($mapping));
                $unmappedColumns = [];
                foreach ($row as $column => $label) {
                    if (!isset($recognizedColumns[$column]) && trim($label) !== '') {
                        $unmappedColumns[$column] = trim($label);
                    }
                }
                continue;
            }

            if (is_array($mapping)) {
                $fields = [];
                foreach ($mapping as $key => $column) {
                    $value = self::cleanCell((string) ($row[$column] ?? ''));
                    if ($value !== '') {
                        $fields[$key][] = $value;
                    }
                }
                foreach ($unmappedColumns as $column => $label) {
                    $value = self::cleanCell((string) ($row[$column] ?? ''));
                    if ($value !== '') {
                        $fields['notes'][] = mb_substr($label . ': ' . $value, 0, self::MAX_CELL_BYTES);
                    }
                }
                $records = array_merge($records, self::buildRecords($fields, $source . ' linha ' . ($rowNumber + 1), $context));
            }
        }
        if ($foundHeader) {
            return $records;
        }

        foreach ($rows as $rowNumber => $row) {
            $fields = [];
            foreach ($row as $cell) {
                foreach (self::pairsFromText($cell) as [$label, $value]) {
                    $key = self::canonicalKey($label);
                    if ($key !== null && $value !== '') {
                        $fields[$key][] = $value;
                    } elseif ($value !== '') {
                        $fields['notes'][] = mb_substr($label . ': ' . $value, 0, self::MAX_CELL_BYTES);
                    }
                }
            }
            $records = array_merge($records, self::buildRecords($fields, $source . ' linha ' . ($rowNumber + 1), $context));
        }

        return $records;
    }

    /** @param string[] $block @return array<int,array<string,mixed>> */
    private static function recordsFromTextBlock(array $block, string $context, string $source): array
    {
        if (!$block) {
            return [];
        }
        $fields = [];
        foreach ($block as $text) {
            foreach (self::pairsFromText($text) as [$label, $value]) {
                $key = self::canonicalKey($label);
                if ($key !== null && $value !== '') {
                    $fields[$key][] = $value;
                } elseif ($value !== '') {
                    $fields['notes'][] = mb_substr($label . ': ' . $value, 0, self::MAX_CELL_BYTES);
                }
            }
        }
        return self::buildRecords($fields, $source, $context);
    }

    /** @return array<int,array{0:string,1:string}> */
    private static function pairsFromText(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $lines = preg_split('/\n+/', $text) ?: [];
        $pairs = [];
        for ($index = 0; $index < count($lines); $index++) {
            $line = trim($lines[$index]);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([^:=]{1,80})\s*[:=]\s*(.*)$/u', $line, $matches)) {
                $value = trim($matches[2]);
                if ($value === '' && isset($lines[$index + 1])) {
                    $value = trim($lines[++$index]);
                }
                $pairs[] = [trim($matches[1]), $value];
                continue;
            }
            $key = self::canonicalKey($line);
            if ($key !== null && isset($lines[$index + 1])) {
                $pairs[] = [$line, trim($lines[++$index])];
            }
        }
        return $pairs;
    }

    /** @param array<string,string[]> $fields @return array<int,array<string,mixed>> */
    private static function buildRecords(array $fields, string $source, string $context): array
    {
        if (!$fields || (!isset($fields['secret_value']) && !isset($fields['username']))) {
            return [];
        }
        foreach ($fields as $key => $values) {
            $expanded = [];
            foreach ($values as $value) {
                $parts = preg_split('/\n+/', $value) ?: [];
                foreach ($parts as $part) {
                    $part = self::cleanCell($part);
                    if ($part !== '') {
                        $expanded[] = $part;
                    }
                }
            }
            $fields[$key] = $expanded;
        }

        $users = $fields['username'] ?? [];
        $secrets = $fields['secret_value'] ?? [];
        $count = max(1, count($users), count($secrets));
        $records = [];
        for ($index = 0; $index < $count; $index++) {
            $title = $fields['title'][min($index, max(0, count($fields['title'] ?? []) - 1))] ?? '';
            if ($title === '' && $context !== '') {
                $title = $context;
            }
            $warnings = [];
            if ($title === '') {
                $warnings[] = 'Nome não identificado.';
            }
            if (($secrets[$index] ?? '') === '') {
                $warnings[] = 'Senha não identificada.';
            }
            if (count($users) !== count($secrets) && count($users) > 0 && count($secrets) > 0) {
                $warnings[] = 'Quantidade de usuários e senhas não coincide; revise a associação.';
            }

            $records[] = [
                'title' => mb_substr($title, 0, 160),
                'username' => mb_substr($users[$index] ?? '', 0, 190),
                'secret_value' => mb_substr($secrets[$index] ?? '', 0, self::MAX_CELL_BYTES),
                'service_url' => mb_substr($fields['service_url'][0] ?? '', 0, 255),
                'ip_internal' => mb_substr($fields['ip_internal'][0] ?? '', 0, 255),
                'ip_external' => mb_substr($fields['ip_external'][0] ?? '', 0, 255),
                'mac' => mb_substr($fields['mac'][0] ?? '', 0, 255),
                'ssid' => mb_substr($fields['ssid'][0] ?? '', 0, 255),
                'notes' => mb_substr(implode("\n", $fields['notes'] ?? []), 0, self::MAX_CELL_BYTES),
                'category_hint' => mb_substr(trim($context . ' ' . $title), 0, 300),
                'source' => $source,
                'warnings' => array_values(array_unique($warnings)),
            ];
        }
        return $records;
    }

    /** @param array<int,array<string,mixed>> $records @return array<int,array<string,mixed>> */
    private static function deduplicateParsedRecords(array $records): array
    {
        $seen = [];
        $result = [];
        foreach ($records as $record) {
            $fingerprint = hash('sha256', implode("\0", [
                self::normalize((string) ($record['title'] ?? '')),
                self::normalize((string) ($record['username'] ?? '')),
                (string) ($record['secret_value'] ?? ''),
                (string) ($record['source'] ?? ''),
            ]));
            if (!isset($seen[$fingerprint])) {
                $seen[$fingerprint] = true;
                $result[] = $record;
            }
        }
        return $result;
    }

    private static function containsRecognizedLabel(string $text): bool
    {
        foreach (preg_split('/[\r\n:=]+/', $text) ?: [] as $part) {
            if (self::canonicalKey($part) !== null) {
                return true;
            }
        }
        return false;
    }

    private static function canonicalKey(string $label): ?string
    {
        $label = self::normalize($label);
        $label = trim((string) preg_replace('/\b(de|da|do|para)\b/', ' ', $label));
        $label = trim((string) preg_replace('/\s+/', ' ', $label));
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = self::normalize($alias);
                $normalizedAlias = trim((string) preg_replace('/\b(de|da|do|para)\b/', ' ', $normalizedAlias));
                $normalizedAlias = trim((string) preg_replace('/\s+/', ' ', $normalizedAlias));
                if ($label === $normalizedAlias) {
                    return $key;
                }
            }
        }
        if (preg_match('/\b(senha|password|segredo|pass)\b/', $label)) return 'secret_value';
        if (preg_match('/\b(usuario|user|login|administrador|admin)\b/', $label)) return 'username';
        if (str_contains($label, 'ip') && preg_match('/\b(externo|publico|wan)\b/', $label)) return 'ip_external';
        if (str_contains($label, 'ip') || preg_match('/\b(lan)\b/', $label)) return 'ip_internal';
        if (str_contains($label, 'mac')) return 'mac';
        if (preg_match('/\b(ssid|wifi)\b/', $label)) return 'ssid';
        if (preg_match('/\b(url|site|link)\b/', $label)) return 'service_url';
        if (preg_match('/\b(observacao|observacoes|notas|descricao)\b/', $label)) return 'notes';
        if (str_contains($label, 'nome') && preg_match('/\b(equipamento|servico|dispositivo|host|servidor|conta)\b/', $label)) return 'title';
        return null;
    }

    private static function canonicalHeaderKey(string $label): ?string
    {
        $label = self::normalize($label);
        $label = trim((string) preg_replace('/\b(de|da|do|para)\b/', ' ', $label));
        $label = trim((string) preg_replace('/\s+/', ' ', $label));
        foreach (self::ALIASES as $key => $aliases) {
            foreach ($aliases as $alias) {
                $normalizedAlias = self::normalize($alias);
                $normalizedAlias = trim((string) preg_replace('/\b(de|da|do|para)\b/', ' ', $normalizedAlias));
                $normalizedAlias = trim((string) preg_replace('/\s+/', ' ', $normalizedAlias));
                if ($label === $normalizedAlias) return $key;
            }
        }
        if (preg_match('/^[a-z ]{1,80}$/', $label)) {
            if (preg_match('/\b(senha|password|segredo|pass)\b/', $label)) return 'secret_value';
            if (preg_match('/\b(usuario|user|login|administrador|admin)\b/', $label)) return 'username';
            if (str_contains($label, 'ip') && preg_match('/\b(externo|publico|wan)\b/', $label)) return 'ip_external';
            if (str_contains($label, 'ip') || preg_match('/\b(lan)\b/', $label)) return 'ip_internal';
            if (str_contains($label, 'mac')) return 'mac';
            if (preg_match('/\b(ssid|wifi)\b/', $label)) return 'ssid';
            if (preg_match('/\b(url|site|link)\b/', $label)) return 'service_url';
            if (preg_match('/\b(observacao|observacoes|notas|descricao)\b/', $label)) return 'notes';
            if (str_contains($label, 'nome') && preg_match('/\b(equipamento|servico|dispositivo|host|servidor|conta)\b/', $label)) return 'title';
        }
        return null;
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = is_string($ascii) ? $ascii : $value;
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $value));
    }

    /** @param array<int,mixed> $row @return string[] */
    private static function sanitizeRow(array $row): array
    {
        $row = array_slice($row, 0, self::MAX_COLUMNS);
        return array_map(static fn ($value): string => self::cleanCell((string) $value), $row);
    }

    private static function cleanCell(string $value): string
    {
        $value = str_replace("\0", '', trim($value));
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        return mb_substr($value, 0, self::MAX_CELL_BYTES);
    }

    private static function loadXml(string $xml, string $format): DOMDocument
    {
        if (strlen($xml) > 8_388_608 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new InvalidArgumentException($format . ' contém XML não permitido.');
        }
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new InvalidArgumentException('Estrutura XML inválida no arquivo ' . $format . '.');
        }
        return $document;
    }
}
