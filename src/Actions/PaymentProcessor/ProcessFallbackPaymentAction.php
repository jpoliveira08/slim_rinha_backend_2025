<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientService;
use RinhaSlim\App\Actions\Payment\StorePaymentAction;
use DateTimeImmutable;
use DateTimeZone;

readonly class ProcessFallbackPaymentAction
{
    public function __construct(
        private string $fallbackProcessorUrl,
        private HttpClientService $httpClient,
        private StorePaymentAction $storePaymentAction
    ) {
    }

    public function execute(array $paymentData): array
    {
        // Make HTTP request using generic client (same as main processor)
        $response = $this->httpClient->post($this->fallbackProcessorUrl, [
            'json' => [
                'correlationId' => $paymentData['correlationId'],
                'amount' => $paymentData['amount'],
                'requestedAt' => $paymentData['createdAt'] ?? new DateTimeImmutable('now', new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z')
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'RinhaSlim-PaymentProcessor/1.0'
            ]
        ]);

        // Handle fallback-specific response logic
        $result = $this->handlePaymentResponse($response, $paymentData);

        // Add processor identifier to the result
        $result['processor'] = 'fallback';
        
        return $result;
    }

    private function handlePaymentResponse(array $response, array $paymentData): array
    {
        $correlationId = $paymentData['correlationId'];
        
        if (!$response['success']) {
            return [
                'success' => false,
                'correlationId' => $correlationId,
                'error' => $response['error'],
                'status' => 'failed',
                'error_type' => $response['error_type']
            ];
        }

        $statusCode = $response['status_code'];
        $body = $response['body'];
        
        if ($statusCode >= 200 && $statusCode < 300) {
            $responseData = json_decode($body, true) ?? [];
            
            $result = [
                'success' => true,
                'correlationId' => $correlationId,
                'transactionId' => $responseData['transactionId'] ?? uniqid('fallback_tx_'),
                'status' => 'approved',
                'amount' => $paymentData['amount'],
                'processor' => 'fallback', // Important for audit
                'response' => $responseData
            ];

            $this->storePaymentAction->execute($result);
            return $result;
        }

        // Check for duplicate correlationId error
        if ($statusCode >= 400 && strpos($body, 'CorrelationId already exists') !== false) {
            return [
                'success' => true,
                'correlationId' => $correlationId,
                'transactionId' => 'duplicate_fallback_' . uniqid(),
                'status' => 'already_processed',
                'message' => 'Payment already processed with this correlationId'
            ];
        }

        return [
            'success' => false,
            'correlationId' => $correlationId,
            'error' => "HTTP {$statusCode}: Fallback payment processor returned error",
            'status' => 'failed',
            'http_code' => $statusCode,
            'response_body' => $body
        ];
    }
}