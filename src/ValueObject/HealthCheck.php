<?php

declare(strict_types=1);

namespace RinhaSlim\App\ValueObject;

/**
 * HealthCheck Value Object
 * 
 * Represents the health status of a payment processor.
 * This is an immutable value object, not a database entity.
 */
final class HealthCheck
{
    public function __construct(
        private readonly bool $failing,
        private readonly int $minResponseTime
    ) {
        if ($minResponseTime < 0) {
            throw new \InvalidArgumentException('Minimum response time cannot be negative');
        }
    }

    public function isFailing(): bool
    {
        return $this->failing;
    }

    public function getMinResponseTime(): int
    {
        return $this->minResponseTime;
    }

    public function isHealthy(): bool
    {
        return !$this->failing;
    }

    public function isFast(): bool
    {
        return $this->minResponseTime < 1000; // Less than 1 second
    }

    public function isOptimal(): bool
    {
        return $this->isHealthy() && $this->isFast();
    }

    /**
     * Check if this processor should be preferred over another
     */
    public function isBetterThan(HealthCheck $other): bool
    {
        // If both are healthy, prefer the faster one
        if ($this->isHealthy() && $other->isHealthy()) {
            return $this->minResponseTime < $other->getMinResponseTime();
        }
        
        // If only one is healthy, prefer the healthy one
        if ($this->isHealthy() && !$other->isHealthy()) {
            return true;
        }
        
        if (!$this->isHealthy() && $other->isHealthy()) {
            return false;
        }
        
        // If both are unhealthy, prefer the one with lower response time
        return $this->minResponseTime < $other->getMinResponseTime();
    }

    public function toArray(): array
    {
        return [
            'failing' => $this->failing,
            'minResponseTime' => $this->minResponseTime,
            'isHealthy' => $this->isHealthy(),
            'isFast' => $this->isFast(),
            'isOptimal' => $this->isOptimal()
        ];
    }

    /**
     * Create from payment processor API response
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            $response['failing'] ?? true,
            $response['minResponseTime'] ?? 5000
        );
    }

    /**
     * Create a failing health check (for error scenarios)
     */
    public static function failing(int $minResponseTime = 5000): self
    {
        return new self(true, $minResponseTime);
    }

    /**
     * Create a healthy health check
     */
    public static function healthy(int $minResponseTime = 100): self
    {
        return new self(false, $minResponseTime);
    }
}
