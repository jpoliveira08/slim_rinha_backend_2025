<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\ValueObject\HealthCheck;

class HealthCheckAction
{
    public function __construct(private HttpClientInterface $httpClient)
    {
    }

    /**
     * Execute health check against a payment processor
     *
     * @param string $processorUrl
     * @return HealthCheck
     * @throws \RuntimeException
     */
    public function execute(string $processorUrl): HealthCheck
    {
        try {
            $this->httpClient->setBaseUrl($processorUrl);
            $this->httpClient->setTimeout(5); // 5 second timeout
            
            $response = $this->httpClient->get('/payments/service-health');

            return new HealthCheck(
                $response['failing'] ?? true,
                $response['minResponseTime'] ?? 5000
            );

        } catch (\Exception $e) {
            // If health check fails, assume the service is failing
            throw new \RuntimeException(
                "Health check failed for {$processorUrl}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
