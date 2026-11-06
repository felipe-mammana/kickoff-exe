<?php

declare(strict_types=1);

final class VaultOperationException extends RuntimeException
{
    public function __construct(
        private readonly string $reason,
        string $safeMessage,
        private readonly bool $retryable = false,
        private readonly ?int $httpStatus = null,
        private readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($safeMessage, 0, $previous);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function retryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
