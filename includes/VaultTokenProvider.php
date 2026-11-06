<?php

declare(strict_types=1);

final class VaultTokenProvider
{
    private const MAX_TOKEN_BYTES = 8192;

    private readonly ?Closure $reader;

    public function __construct(
        private readonly string $tokenFile,
        ?callable $reader = null
    ) {
        if (trim($tokenFile) === '') {
            throw new InvalidArgumentException('Arquivo de token do Vault não configurado.');
        }
        $this->reader = $reader !== null ? Closure::fromCallable($reader) : null;
    }

    public function token(): string
    {
        try {
            $contents = $this->reader !== null
                ? ($this->reader)($this->tokenFile)
                : $this->readTokenFile();
        } catch (VaultOperationException $error) {
            throw $error;
        } catch (Throwable) {
            // Filesystem/provider exceptions must not leak the path or token.
            throw new VaultOperationException('token_unavailable', 'Token de acesso ao Vault indisponível.');
        }

        if (!is_string($contents)) {
            throw new VaultOperationException('token_unavailable', 'Token de acesso ao Vault indisponível.');
        }

        $token = trim($contents);
        if ($token === '' || strlen($token) > self::MAX_TOKEN_BYTES || preg_match('/^[\x21-\x7E]+$/D', $token) !== 1) {
            throw new VaultOperationException('token_invalid', 'Token de acesso ao Vault inválido.');
        }

        return $token;
    }

    private function readTokenFile(): string|false
    {
        $before = @lstat($this->tokenFile);
        if (!is_array($before)) {
            throw new VaultOperationException('token_unavailable', 'Token de acesso ao Vault indisponível.');
        }

        $handle = @fopen($this->tokenFile, 'rb');
        if ($handle === false) {
            throw new VaultOperationException('token_unavailable', 'Token de acesso ao Vault indisponível.');
        }

        try {
            $opened = @fstat($handle);
            if (!is_array($opened) || !self::sameFile($before, $opened)) {
                throw new VaultOperationException('token_changed', 'Arquivo de token do Vault foi substituído durante a leitura.');
            }

            // Read at most one byte beyond the limit so oversized files are rejected
            // without ever loading their full contents into memory.
            $contents = @stream_get_contents($handle, self::MAX_TOKEN_BYTES + 1);
            if (!is_string($contents)) {
                throw new VaultOperationException('token_unavailable', 'Token de acesso ao Vault indisponível.');
            }

            $afterHandle = @fstat($handle);
            $afterPath = @lstat($this->tokenFile);
            if (
                !is_array($afterHandle)
                || !is_array($afterPath)
                || !self::sameFile($opened, $afterHandle)
                || !self::sameFile($opened, $afterPath)
            ) {
                throw new VaultOperationException('token_changed', 'Arquivo de token do Vault foi substituído durante a leitura.');
            }

            return $contents;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string, int> $first @param array<string, int> $second */
    private static function sameFile(array $first, array $second): bool
    {
        if (isset($first['dev'], $first['ino'], $second['dev'], $second['ino']) && $first['ino'] !== 0 && $second['ino'] !== 0) {
            return $first['dev'] === $second['dev'] && $first['ino'] === $second['ino'];
        }

        // Fallback for filesystems that do not expose a usable inode (notably some
        // Windows filesystems). Comparing timestamps and size still detects normal
        // Agent replacements and in-place changes.
        foreach (['size', 'mtime', 'ctime'] as $field) {
            if (!isset($first[$field], $second[$field]) || $first[$field] !== $second[$field]) {
                return false;
            }
        }
        return true;
    }
}
