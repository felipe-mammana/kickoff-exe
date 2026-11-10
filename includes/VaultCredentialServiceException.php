<?php

declare(strict_types=1);

final class VaultCredentialServiceException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        string $safeMessage,
        private readonly bool $conflict = false,
        ?Throwable $previous = null
    ) {
        parent::__construct($safeMessage, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function isConflict(): bool
    {
        return $this->conflict;
    }
}
