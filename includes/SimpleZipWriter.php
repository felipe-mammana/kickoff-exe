<?php

declare(strict_types=1);

class SimpleZipWriter
{
    /** @var array<int, array{name: string, crc: int, size: int, offset: int}> */
    private array $entries = [];
    private string $contents = '';

    public function addFile(string $name, string $contents): void
    {
        $name = str_replace('\\', '/', ltrim($name, '/'));
        $crc = crc32($contents);
        $size = strlen($contents);
        $offset = strlen($this->contents);

        $this->contents .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 0, $crc, $size, $size, strlen($name), 0);
        $this->contents .= $name . $contents;
        $this->entries[] = [
            'name' => $name,
            'crc' => $crc,
            'size' => $size,
            'offset' => $offset,
        ];
    }

    public function output(): string
    {
        $central = '';
        foreach ($this->entries as $entry) {
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                0,
                $entry['crc'],
                $entry['size'],
                $entry['size'],
                strlen($entry['name']),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            );
            $central .= $entry['name'];
        }

        $centralOffset = strlen($this->contents);
        $centralSize = strlen($central);

        return $this->contents
            . $central
            . pack('VvvvvVVv', 0x06054b50, 0, 0, count($this->entries), count($this->entries), $centralSize, $centralOffset, 0);
    }
}
