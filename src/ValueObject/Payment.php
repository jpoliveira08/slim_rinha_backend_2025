<?php

declare(strict_types=1);

namespace RinhaSlim\App\ValueObject;

use DateTimeInterface;

/**
 * Payment Value Object
 * 
 * Represents payment data for processing.
 * This is an immutable value object representing the payment request.
 */
final class Payment
{
    public function __construct(
        private readonly string $correlationId,
        private readonly float $amount,
        private readonly DateTimeInterface $requestedAt
    ) {
        $this->validateCorrelationId($correlationId);
        $this->validateAmount($amount);
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

    public function toArray(): array
    {
        return [
            'correlationId' => $this->correlationId,
            'amount' => $this->amount,
            'requestedAt' => $this->requestedAt->format('c') // ISO 8601
        ];
    }

    /**
     * Create from API request data
     */
    public static function fromArray(array $data): self
    {
        $requestedAt = isset($data['requestedAt']) 
            ? new \DateTime($data['requestedAt'])
            : new \DateTime();

        return new self(
            $data['correlationId'],
            (float) $data['amount'],
            $requestedAt
        );
    }

    private function validateCorrelationId(string $correlationId): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $correlationId)) {
            throw new \InvalidArgumentException('Invalid correlation ID format. Must be a valid UUID.');
        }
    }

    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
    }
}
