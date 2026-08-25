<?php

declare(strict_types=1);

class CompanyEquipmentDocxExporter
{
    private const EMU_PER_PIXEL = 9525;
    private const MAX_IMAGE_WIDTH_EMU = 5200000;
    private const MAX_IMAGE_HEIGHT_EMU = 3200000;

    /** @var array<int, array{path: string, name: string, extension: string, content_type: string, rid: string, width: int, height: int}> */
    private array $images = [];

    public function build(array $company, array $machines, array $photosByMachine): string
    {
        $document = $this->documentXml($company, $machines, $photosByMachine);
        $zip = new SimpleZipWriter();
        $zip->addFile('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFile('_rels/.rels', $this->rootRelsXml());
        $zip->addFile('docProps/app.xml', $this->appXml());
        $zip->addFile('docProps/core.xml', $this->coreXml());
        $zip->addFile('word/document.xml', $document);
        $zip->addFile('word/styles.xml', $this->stylesXml());
        $zip->addFile('word/_rels/document.xml.rels', $this->documentRelsXml());

        foreach ($this->images as $index => $image) {
            $zip->addFile('word/media/image' . ($index + 1) . '.' . $image['extension'], file_get_contents($image['path']) ?: '');
        }

        return $zip->output();
    }

    private function documentXml(array $company, array $machines, array $photosByMachine): string
    {
        $this->images = [];
        $companyName = (string) ($company['name'] ?? 'Empresa');
        $deviceTypes = Machine::deviceTypes();
        $grouped = [];

        foreach ($machines as $machine) {
            $type = (string) ($machine['device_type'] ?? 'outros');
            $grouped[$type][] = $machine;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" mc:Ignorable="w14 wp14"><w:body>';

        $xml .= $this->paragraph($companyName . ' - Equipamentos de Rede', 'Title');
        $xml .= $this->paragraph('Relatorio gerado em ' . date('d/m/Y H:i') . '.', 'Subtitle');
        $xml .= $this->paragraph('O documento organiza os equipamentos por categoria e inclui os principais dados tecnicos, checklist operacional e fotos cadastradas. Senhas de equipamentos nao sao exportadas por seguranca.', 'Normal');
        $xml .= $this->summaryTable([
            ['Empresa', $companyName],
            ['Total de equipamentos', (string) count($machines)],
            ['Categorias no relatorio', (string) count($grouped)],
        ]);

        if (!$machines) {
            $xml .= $this->heading('Nenhum equipamento encontrado', 1);
            $xml .= $this->paragraph('Nao existem equipamentos cadastrados para os filtros selecionados.', 'Normal');
        }

        foreach ($grouped as $type => $items) {
            $xml .= $this->heading(($deviceTypes[$type] ?? 'Dispositivo') . ' (' . count($items) . ')', 1);
            foreach ($items as $machine) {
                $xml .= $this->heading($this->machineTitle($machine), 2);
                $xml .= $this->dataTable($this->machineFields($machine));
                $photos = $photosByMachine[(int) $machine['id']] ?? [];
                $xml .= $this->photosSection($photos);
            }
        }

        $xml .= '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>';
        $xml .= '</w:body></w:document>';

        return $xml;
    }

    private function machineFields(array $machine): array
    {
        $type = (string) ($machine['device_type'] ?? 'outros');
        $base = [
            ['Etiqueta', $this->value($machine, 'tag')],
            ['Hostname novo', $this->value($machine, 'new_hostname')],
            ['Hostname antigo', $this->value($machine, 'old_hostname')],
            ['Modelo', $this->value($machine, 'computer_model')],
        ];

        if (in_array($type, ['notebook', 'cpu'], true)) {
            return array_merge($base, [
                ['Colaborador', $this->value($machine, 'employee_name')],
                ['Departamento', $this->value($machine, 'department')],
                ['Sistema operacional', $this->value($machine, 'operating_system')],
                ['TFlux instalado', !empty($machine['tflux_installed']) ? 'Sim' : 'Nao'],
                ['Antivirus instalado', !empty($machine['antivirus_installed']) ? 'Sim' : 'Nao'],
                ['Solicitante no TFlux', !empty($machine['requester_in_tflux']) ? 'Sim' : 'Nao'],
            ]);
        }

        if (in_array($type, ['roteador', 'modem'], true)) {
            return array_merge($base, [
                ['Usuario administrador', $this->value($machine, 'admin_user')],
                ['IP de acesso', $this->value($machine, 'ip_address')],
                ['Gateway', $this->value($machine, 'gateway')],
                ['Operadora', $this->value($machine, 'carrier')],
            ]);
        }

        if ($type === 'access_point') {
            return array_merge($base, [
                ['Local de instalacao', $this->value($machine, 'install_location')],
                ['IP de acesso', $this->value($machine, 'ip_address')],
            ]);
        }

        if ($type === 'impressora') {
            return array_merge($base, [
                ['Marca', $this->value($machine, 'brand') !== '-' ? $this->value($machine, 'brand') : $this->value($machine, 'printer_brand')],
                ['Tipo de conexao', $this->value($machine, 'printer_connection_type')],
                ['IP', $this->value($machine, 'ip_address')],
                ['Gateway', $this->value($machine, 'gateway')],
                ['Compartilhada', !empty($machine['printer_shared']) ? 'Sim' : 'Nao'],
            ]);
        }

        return array_merge($base, [
            ['Marca', $this->value($machine, 'brand') !== '-' ? $this->value($machine, 'brand') : $this->value($machine, 'printer_brand')],
            ['Local / responsavel', $this->value($machine, 'install_location')],
            ['Observacoes', $this->value($machine, 'notes')],
        ]);
    }

    private function photosSection(array $photos): string
    {
        if (!$photos) {
            return $this->paragraph('Fotos: nenhuma foto cadastrada.', 'Caption');
        }

        $xml = $this->paragraph('Fotos cadastradas', 'Heading3');
        foreach ($photos as $photo) {
            $path = UPLOAD_PATH . '/' . (string) ($photo['file_name'] ?? '');
            $labelParts = [MachinePhoto::topicLabel($photo['photo_topic'] ?? 'equipamento')];
            if (($photo['photo_type'] ?? 'general') === 'network_config') {
                $labelParts[] = 'Rede';
            }
            $labelParts[] = (string) (($photo['original_name'] ?? '') ?: ($photo['file_name'] ?? 'foto'));
            $label = implode(' - ', $labelParts);

            if (!is_file($path)) {
                $xml .= $this->paragraph($label . ' (arquivo nao encontrado)', 'Caption');
                continue;
            }

            $image = $this->registerImage($path, (string) ($photo['mime_type'] ?? ''));
            if (!$image) {
                $xml .= $this->paragraph($label . ' (formato nao suportado no DOCX)', 'Caption');
                continue;
            }

            $xml .= $this->paragraph($label, 'Caption');
            $xml .= $this->imageParagraph($image);
        }

        return $xml;
    }

    private function registerImage(string $path, string $mime): ?array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => '',
        };

        if ($contentType === '') {
            return null;
        }

        $size = @getimagesize($path);
        if (!$size || empty($size[0]) || empty($size[1])) {
            return null;
        }

        $width = (int) $size[0] * self::EMU_PER_PIXEL;
        $height = (int) $size[1] * self::EMU_PER_PIXEL;
        $scale = min(1, self::MAX_IMAGE_WIDTH_EMU / $width, self::MAX_IMAGE_HEIGHT_EMU / $height);
        $index = count($this->images) + 1;
        $image = [
            'path' => $path,
            'name' => 'image' . $index . '.' . ($extension === 'jpeg' ? 'jpg' : $extension),
            'extension' => $extension === 'jpeg' ? 'jpg' : $extension,
            'content_type' => $contentType,
            'rid' => 'rIdImage' . $index,
            'width' => max(1, (int) floor($width * $scale)),
            'height' => max(1, (int) floor($height * $scale)),
        ];
        $this->images[] = $image;

        return $image;
    }

    private function imageParagraph(array $image): string
    {
        $id = (string) count($this->images);
        $name = $this->xml($image['name']);

        return '<w:p><w:pPr><w:spacing w:after="180"/></w:pPr><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . '<wp:extent cx="' . $image['width'] . '" cy="' . $image['height'] . '"/><wp:docPr id="' . $id . '" name="' . $name . '"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
            . '<pic:nvPicPr><pic:cNvPr id="' . $id . '" name="' . $name . '"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $image['rid'] . '"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $image['width'] . '" cy="' . $image['height'] . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    private function dataTable(array $rows): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="D7DEE8"/><w:left w:val="single" w:sz="4" w:color="D7DEE8"/><w:bottom w:val="single" w:sz="4" w:color="D7DEE8"/><w:right w:val="single" w:sz="4" w:color="D7DEE8"/><w:insideH w:val="single" w:sz="4" w:color="D7DEE8"/><w:insideV w:val="single" w:sz="4" w:color="D7DEE8"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="2700"/><w:gridCol w:w="6300"/></w:tblGrid>';
        foreach ($rows as $row) {
            $xml .= '<w:tr>' . $this->cell($row[0], true) . $this->cell($row[1], false) . '</w:tr>';
        }

        return $xml . '</w:tbl>' . $this->paragraph('', 'Normal');
    }

    private function summaryTable(array $rows): string
    {
        return $this->dataTable($rows);
    }

    private function cell(string $text, bool $heading): string
    {
        $fill = $heading ? '<w:shd w:fill="EAF2FF"/>' : '';
        $style = $heading ? 'TableLabel' : 'Normal';

        return '<w:tc><w:tcPr><w:tcW w:w="' . ($heading ? '2700' : '6300') . '" w:type="dxa"/>' . $fill . '<w:tcMar><w:top w:w="90" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:bottom w:w="90" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tcMar></w:tcPr>'
            . $this->paragraph($text, $style)
            . '</w:tc>';
    }

    private function heading(string $text, int $level): string
    {
        return $this->paragraph($text, $level === 1 ? 'Heading1' : 'Heading2');
    }

    private function paragraph(string $text, string $style): string
    {
        $text = $this->xml($text);
        $runPr = in_array($style, ['Title', 'Heading1', 'Heading2', 'Heading3', 'TableLabel'], true) ? '<w:b/>' : '';

        return '<w:p><w:pPr><w:pStyle w:val="' . $style . '"/></w:pPr><w:r><w:rPr>' . $runPr . '</w:rPr><w:t xml:space="preserve">' . $text . '</w:t></w:r></w:p>';
    }

    private function machineTitle(array $machine): string
    {
        foreach (['tag', 'new_hostname', 'old_hostname', 'modem_name', 'install_location', 'computer_model'] as $field) {
            if (!empty($machine[$field])) {
                return (string) $machine[$field];
            }
        }

        return 'Dispositivo #' . (int) ($machine['id'] ?? 0);
    }

    private function value(array $data, string $field): string
    {
        $value = trim((string) ($data[$field] ?? ''));

        return $value === '' ? '-' : $value;
    }

    private function contentTypesXml(): string
    {
        $imageTypes = '';
        $seen = [];
        foreach ($this->images as $image) {
            if (isset($seen[$image['extension']])) {
                continue;
            }
            $seen[$image['extension']] = true;
            $imageTypes .= '<Default Extension="' . $this->xml($image['extension']) . '" ContentType="' . $this->xml($image['content_type']) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . $imageTypes
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function documentRelsXml(): string
    {
        $rels = '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        foreach ($this->images as $index => $image) {
            $rels .= '<Relationship Id="' . $image['rid'] . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image' . ($index + 1) . '.' . $this->xml($image['extension']) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . $this->style('Normal', 'paragraph', 'Normal', 21, '222222', false, 120, 70)
            . $this->style('Title', 'paragraph', 'Title', 34, '005CAA', true, 0, 220)
            . $this->style('Subtitle', 'paragraph', 'Subtitle', 21, '657181', false, 0, 280)
            . $this->style('Heading1', 'paragraph', 'Heading 1', 28, '005CAA', true, 340, 150)
            . $this->style('Heading2', 'paragraph', 'Heading 2', 23, '1F2937', true, 240, 120)
            . $this->style('Heading3', 'paragraph', 'Heading 3', 20, '1F2937', true, 140, 80)
            . $this->style('Caption', 'paragraph', 'Caption', 18, '657181', false, 80, 80)
            . $this->style('TableLabel', 'paragraph', 'Table Label', 19, '1F2937', true, 0, 0)
            . '</w:styles>';
    }

    private function style(string $id, string $type, string $name, int $size, string $color, bool $bold, int $before, int $after): string
    {
        return '<w:style w:type="' . $type . '" w:styleId="' . $id . '"><w:name w:val="' . $this->xml($name) . '"/><w:pPr><w:spacing w:before="' . $before . '" w:after="' . $after . '" w:line="276" w:lineRule="auto"/></w:pPr><w:rPr>'
            . ($bold ? '<w:b/>' : '')
            . '<w:color w:val="' . $color . '"/><w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/><w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/></w:rPr></w:style>';
    }

    private function coreXml(): string
    {
        $now = date('c');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Relatorio de equipamentos</dc:title><dc:creator>EXE Inventario TI</dc:creator><cp:lastModifiedBy>EXE Inventario TI</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>EXE Inventario TI</Application></Properties>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
