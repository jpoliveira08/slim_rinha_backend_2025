<?php

declare(strict_types=1);

namespace RinhaSlim\App\Model;

use DateTimeInterface;

/**
 * PaymentRecord Entity
 * 
 * Represents a payment record stored in the database.
 * This is an actual database entity/model.
 */
class PaymentRecord
{
    public function __construct(
        private ?int $id,
        private string $correlationId,
        private float $amount,
        private DateTimeInterface $requestedAt,
        private DateTimeInterface $processedAt,
        private string $processor,
        private string $status
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getRequestedAt(): DateTimeInterface
    {
        return $this->requestedAt;
    }

    public function getProcessedAt(): DateTimeInterface
    {
        return $this->processedAt;
    }

    public function getProcessor(): string
    {
        return $this->processor;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setProcessedAt(DateTimeInterface $processedAt): void
    {
        $this->processedAt = $processedAt;
    }

    public function setProcessor(string $processor): void
    {
        $this->processor = $processor;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'correlationId' => $this->correlationId,
            'amount' => $this->amount,
            'requestedAt' => $this->requestedAt->format('c'),
            'processedAt' => $this->processedAt->format('c'),
            'processor' => $this->processor,
            'status' => $this->status
        ];
    }
}
