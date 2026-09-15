<?php
declare(strict_types=1);

class BackupRecovery
{
    // Restore only into a new staging directory, never over the running application.
    public static function stage(string $encrypted, string $destination): array
    {
        if (!class_exists('ZipArchive')) throw new RuntimeException('Habilite a extensao PHP zip para recuperar anexos.');
        if (file_exists($destination)) throw new RuntimeException('Destino deve ser novo.');
        $plain = EncryptedBackup::decrypt($encrypted, 'zip');
        $temp = tempnam(sys_get_temp_dir(), 'exe-recovery-');
        if ($temp === false) throw new RuntimeException('Falha no arquivo temporario.');
        $zip = new ZipArchive();
        $opened = false;
        try {
            if (file_put_contents($temp, $plain) !== strlen($plain)) throw new RuntimeException('Falha no arquivo temporario.');
            if ($zip->open($temp) !== true) throw new RuntimeException('ZIP invalido.');
            $opened = true;
            $entries = [];
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $name = $entry['name'];
                $allowed = $name === 'database-full.sql' || $name === 'manifest.json'
                    || preg_match('#^(storage/(machine_photos|company_attachments)|public/uploads)/[^/\\\\]+$#D', $name);
                if (!$allowed || str_contains($name, '..') || str_contains($name, ':') || str_contains($name, "\0") || isset($entries[strtolower($name)])) {
                    throw new RuntimeException('Caminho inseguro ou duplicado no backup.');
                }
                $total += (int) $entry['size'];
                if ($total > 512 * 1024 * 1024 || $i >= 10000) throw new RuntimeException('Limite de recuperacao excedido.');
                $entries[strtolower($name)] = $name;
            }
            if (!isset($entries['database-full.sql'], $entries['manifest.json'])) throw new RuntimeException('Backup incompleto.');
            if (!mkdir($destination, 0700, true)) throw new RuntimeException('Falha ao criar destino.');
            $hashes = [];
            foreach ($entries as $name) {
                $contents = $zip->getFromName($name);
                if ($contents === false) throw new RuntimeException('Falha ao ler entrada.');
                $path = $destination . '/' . $name;
                if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) throw new RuntimeException('Falha ao criar subdiretorio.');
                if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) throw new RuntimeException('Falha ao recuperar arquivo.');
                $hashes[$name] = hash_file('sha256', $path);
                if (!hash_equals(hash('sha256', $contents), $hashes[$name])) throw new RuntimeException('Arquivo recuperado diverge.');
            }
            return $hashes;
        } finally {
            if ($opened) $zip->close();
            unlink($temp);
        }
    }
}
