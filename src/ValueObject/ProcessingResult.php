<?php

declare(strict_types=1);

namespace RinhaSlim\App\ValueObject;

/**
 * ProcessingResult Value Object
 * 
 * Represents the result of a payment processing operation.
 * This is an immutable value object, not a database entity.
 */
final class ProcessingResult
{
    public function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly ?string $processorUsed = null,
        private readonly ?string $correlationId = null
    ) {
        if (empty(trim($message))) {
            throw new \InvalidArgumentException('Message cannot be empty');
        }
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getProcessorUsed(): ?string
    {
        return $this->processorUsed;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function isQueued(): bool
    {
        return $this->message === 'queued' || 
               str_contains(strtolower($this->message), 'queued');
    }

    public function isFailure(): bool
    {
        return !$this->success;
    }

    public static function success(
        string $message = 'processed successfully', 
        ?string $processorUsed = null,
        ?string $correlationId = null
    ): self {
        return new self(true, $message, $processorUsed, $correlationId);
    }

    public static function failure(
        string $message, 
        ?string $processorUsed = null,
        ?string $correlationId = null
    ): self {
        return new self(false, $message, $processorUsed, $correlationId);
    }

    public static function queued(
        string $correlationId,
        string $message = 'Payment queued for processing'
    ): self {
        return new self(true, $message, 'queued', $correlationId);
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'processorUsed' => $this->processorUsed,
            'correlationId' => $this->correlationId,
            'isQueued' => $this->isQueued()
        ];
    }
}
