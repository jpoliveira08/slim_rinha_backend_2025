<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientService;

readonly class ProcessFallbackPaymentAction
{
    public function __construct(
        private string $fallbackProcessorUrl,
        private HttpClientService $httpClient
    ) {
    }

    public function execute(array $paymentData): array
    {
        // For testing, let's use simulation first, then real HTTP
        if ($this->fallbackProcessorUrl === 'SIMULATE' || str_contains($this->fallbackProcessorUrl, 'localhost')) {
            // Keep simulation for testing - fallback usually more reliable
            $success = rand(1, 100) > 15; // 85% success rate
            
            if ($success) {
                return [
                    'success' => true,
                    'correlationId' => $paymentData['correlationId'],
                    'transactionId' => uniqid('fallback_sim_'),
                    'status' => 'approved',
                    'processor' => 'fallback'
                ];
            }
            
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => 'Simulated fallback processor temporarily unavailable',
                'status' => 'failed',
                'processor' => 'fallback'
            ];
        }

        // Real HTTP call to external fallback payment processor
        $result = $this->httpClient->makePaymentRequest($this->fallbackProcessorUrl, $paymentData);
        
        // Add processor identifier to the result
        $result['processor'] = 'fallback';
        
        return $result;
    }
}