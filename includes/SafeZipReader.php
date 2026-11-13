<?php

declare(strict_types=1);

final class SafeZipReader
{
    private const MAX_ENTRIES = 500;
    private const MAX_CENTRAL_DIRECTORY_BYTES = 4_194_304;
    private const MAX_ENTRY_BYTES = 10_485_760;
    private const MAX_TOTAL_BYTES = 26_214_400;
    private const MAX_COMPRESSION_RATIO = 120;

    private string $contents;

    /** @var array<string, array{flags:int,method:int,crc:int,compressed:int,uncompressed:int,offset:int}> */
    private array $entries = [];

    private int $declaredTotal = 0;

    public function __construct(string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Arquivo compactado indisponível para leitura.');
        }

        $size = filesize($path);
        if ($size === false || $size <= 0 || $size > 12_582_912) {
            throw new RuntimeException('Arquivo compactado vazio ou acima do limite permitido.');
        }

        $contents = file_get_contents($path);
        if (!is_string($contents) || strlen($contents) !== $size) {
            throw new RuntimeException('Não foi possível ler o arquivo compactado.');
        }

        $this->contents = $contents;
        $this->parseCentralDirectory();
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->entries);
    }

    public function has(string $name): bool
    {
        return isset($this->entries[$this->normalizeName($name)]);
    }

    public function get(string $name, int $maxBytes = self::MAX_ENTRY_BYTES): string
    {
        $name = $this->normalizeName($name);
        $entry = $this->entries[$name] ?? null;
        if ($entry === null) {
            throw new RuntimeException('Estrutura interna obrigatória ausente no arquivo.');
        }
        if ($entry['uncompressed'] > min($maxBytes, self::MAX_ENTRY_BYTES)) {
            throw new RuntimeException('Conteúdo interno acima do limite permitido.');
        }

        $offset = $entry['offset'];
        if ($offset < 0 || $offset + 30 > strlen($this->contents)) {
            throw new RuntimeException('Posição de entrada ZIP inválida.');
        }
        if (substr($this->contents, $offset, 4) !== "PK\x03\x04") {
            throw new RuntimeException('Estrutura ZIP local inválida.');
        }

        $header = unpack(
            'vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength',
            substr($this->contents, $offset + 4, 26)
        );
        if (!is_array($header)) {
            throw new RuntimeException('Cabeçalho ZIP local inválido.');
        }
        if ((int) $header['method'] !== $entry['method'] || (((int) $header['flags']) & 0x0001) !== 0) {
            throw new RuntimeException('Cabeçalho ZIP local inconsistente.');
        }

        $dataOffset = $offset + 30 + (int) $header['nameLength'] + (int) $header['extraLength'];
        $compressed = substr($this->contents, $dataOffset, $entry['compressed']);
        if (strlen($compressed) !== $entry['compressed']) {
            throw new RuntimeException('Conteúdo ZIP truncado.');
        }

        if ($entry['method'] === 0) {
            $plain = $compressed;
        } elseif ($entry['method'] === 8) {
            $plain = gzinflate($compressed, $entry['uncompressed']);
            if ($plain === false) {
                throw new RuntimeException('Não foi possível descompactar o conteúdo do arquivo.');
            }
        } else {
            throw new RuntimeException('Método de compactação não suportado.');
        }

        if (strlen($plain) !== $entry['uncompressed']) {
            throw new RuntimeException('Tamanho interno do arquivo compactado é inconsistente.');
        }

        $crc = (int) hexdec(hash('crc32b', $plain));
        if (($crc & 0xffffffff) !== ($entry['crc'] & 0xffffffff)) {
            throw new RuntimeException('Falha de integridade no conteúdo compactado.');
        }

        return $plain;
    }

    private function parseCentralDirectory(): void
    {
        $searchStart = max(0, strlen($this->contents) - 65_557);
        $eocdOffset = strrpos(substr($this->contents, $searchStart), "PK\x05\x06");
        if ($eocdOffset === false) {
            throw new RuntimeException('Arquivo ZIP inválido ou protegido por senha.');
        }
        $eocdOffset += $searchStart;
        $eocd = unpack(
            'vdisk/vcentralDisk/ventriesDisk/ventriesTotal/VcentralSize/VcentralOffset/vcommentLength',
            substr($this->contents, $eocdOffset + 4, 18)
        );
        if (!is_array($eocd)
            || (int) $eocd['disk'] !== 0
            || (int) $eocd['centralDisk'] !== 0
            || (int) $eocd['entriesDisk'] !== (int) $eocd['entriesTotal']
        ) {
            throw new RuntimeException('Arquivos ZIP divididos em volumes não são aceitos.');
        }
        if ($eocdOffset + 22 + (int) $eocd['commentLength'] !== strlen($this->contents)) {
            throw new RuntimeException('Final do arquivo ZIP é inconsistente.');
        }

        $entryCount = (int) $eocd['entriesTotal'];
        $centralSize = (int) $eocd['centralSize'];
        $centralOffset = (int) $eocd['centralOffset'];
        if ($entryCount <= 0 || $entryCount > self::MAX_ENTRIES
            || $centralSize <= 0 || $centralSize > self::MAX_CENTRAL_DIRECTORY_BYTES
            || $centralOffset < 0 || $centralOffset + $centralSize > strlen($this->contents)
        ) {
            throw new RuntimeException('Estrutura ZIP excede os limites de segurança.');
        }

        $cursor = $centralOffset;
        for ($index = 0; $index < $entryCount; $index++) {
            if (substr($this->contents, $cursor, 4) !== "PK\x01\x02") {
                throw new RuntimeException('Diretório central ZIP inválido.');
            }

            $header = unpack(
                'vversionMade/vversionNeeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vuncompressed/vnameLength/vextraLength/vcommentLength/vdiskStart/vinternal/Vexternal/VlocalOffset',
                substr($this->contents, $cursor + 4, 42)
            );
            if (!is_array($header)) {
                throw new RuntimeException('Entrada ZIP inválida.');
            }

            $nameLength = (int) $header['nameLength'];
            $extraLength = (int) $header['extraLength'];
            $commentLength = (int) $header['commentLength'];
            $name = substr($this->contents, $cursor + 46, $nameLength);
            $name = $this->normalizeName($name);
            $flags = (int) $header['flags'];
            $method = (int) $header['method'];
            $compressed = (int) $header['compressed'];
            $uncompressed = (int) $header['uncompressed'];

            if ((int) $header['versionNeeded'] >= 45) {
                throw new RuntimeException('ZIP64 não é aceito na importação.');
            }

            if (($flags & 0x0001) !== 0) {
                throw new RuntimeException('Arquivos protegidos por senha não são aceitos.');
            }
            if (($flags & 0x0040) !== 0 || ($flags & 0x2000) !== 0) {
                throw new RuntimeException('Criptografia ZIP não suportada.');
            }
            if (!in_array($method, [0, 8], true)) {
                throw new RuntimeException('Método de compactação não suportado.');
            }
            if ($compressed < 0 || $uncompressed < 0
                || $compressed === 0xffffffff || $uncompressed === 0xffffffff
                || $uncompressed > self::MAX_ENTRY_BYTES
            ) {
                throw new RuntimeException('Entrada ZIP acima do limite permitido.');
            }
            if ($compressed > 0 && $uncompressed / $compressed > self::MAX_COMPRESSION_RATIO) {
                throw new RuntimeException('Taxa de compactação suspeita; arquivo rejeitado.');
            }

            $this->declaredTotal += $uncompressed;
            if ($this->declaredTotal > self::MAX_TOTAL_BYTES) {
                throw new RuntimeException('Conteúdo descompactado acima do limite permitido.');
            }

            if (!str_ends_with($name, '/')) {
                if (isset($this->entries[$name])) {
                    throw new RuntimeException('Arquivo ZIP contém entradas duplicadas.');
                }
                $this->entries[$name] = [
                    'flags' => $flags,
                    'method' => $method,
                    'crc' => (int) $header['crc'],
                    'compressed' => $compressed,
                    'uncompressed' => $uncompressed,
                    'offset' => (int) $header['localOffset'],
                ];
            }

            $cursor += 46 + $nameLength + $extraLength + $commentLength;
            if ($cursor > $centralOffset + $centralSize) {
                throw new RuntimeException('Diretório central ZIP truncado.');
            }
        }
        if ($cursor !== $centralOffset + $centralSize) {
            throw new RuntimeException('Diretório central ZIP inconsistente.');
        }
    }

    private function normalizeName(string $name): string
    {
        $name = str_replace('\\', '/', trim($name));
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/')
            || preg_match('/(^|\/)\.\.?(\/|$)/', $name)
            || preg_match('/^[A-Za-z]:/', $name)
        ) {
            throw new RuntimeException('Caminho interno inválido no arquivo compactado.');
        }

        return $name;
    }
}
