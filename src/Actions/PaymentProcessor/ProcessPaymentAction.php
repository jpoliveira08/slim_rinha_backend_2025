<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientService;

readonly class ProcessPaymentAction
{
    public function __construct(
        private string $processorUrl,
        private HttpClientService $httpClient
    )
    {
    }

    public function execute(array $paymentData): array
    {
        // For testing, let's use simulation first, then real HTTP
        if ($this->processorUrl === 'SIMULATE' || str_contains($this->processorUrl, 'localhost')) {
            // Keep simulation for testing
            $success = rand(1, 100) > 30; // 70% success rate
            
            if ($success) {
                return [
                    'success' => true,
                    'correlationId' => $paymentData['correlationId'],
                    'transactionId' => uniqid('sim_tx_'),
                    'status' => 'approved'
                ];
            }
            
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => 'Simulated payment processor temporarily unavailable',
                'status' => 'failed'
            ];
        }

        // Real HTTP call to external payment processor
        return $this->httpClient->makePaymentRequest($this->processorUrl, $paymentData);
    }
}
