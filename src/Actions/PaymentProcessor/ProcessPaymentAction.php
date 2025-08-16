<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\ValueObject\Payment;
use RinhaSlim\App\ValueObject\ProcessingResult;

class ProcessPaymentAction
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Execute payment processing against a payment processor
     *
     * @param string $processorUrl
     * @param Payment $payment
     * @return ProcessingResult
     */
    public function execute(string $processorUrl, Payment $payment): ProcessingResult
    {
        try {
            $this->httpClient->setBaseUrl($processorUrl);
            $this->httpClient->setTimeout(10); // 10 second timeout
            
            $requestData = [
                'correlationId' => $payment->getCorrelationId(),
                'amount' => $payment->getAmount(),
                'requestedAt' => $payment->getRequestedAt()->format('c') // ISO 8601 format
            ];

            $response = $this->httpClient->post('/payments', $requestData);

            return ProcessingResult::success(
                $response['message'] ?? 'payment processed successfully',
                $this->extractProcessorType($processorUrl),
                $payment->getCorrelationId()
            );

        } catch (\Exception $e) {
            return ProcessingResult::failure(
                "Payment processing failed: " . $e->getMessage(),
                $this->extractProcessorType($processorUrl),
                $payment->getCorrelationId()
            );
        }
    }

    /**
     * Extract processor type from URL for tracking
     *
     * @param string $processorUrl
     * @return string
     */
    private function extractProcessorType(string $processorUrl): string
    {
        if (str_contains($processorUrl, 'fallback')) {
            return 'fallback';
        }
        
        if (str_contains($processorUrl, 'default')) {
            return 'default';
        }

        return 'unknown';
    }
}
