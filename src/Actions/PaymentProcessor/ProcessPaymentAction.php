<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientService;
use DateTimeImmutable;
use DateTimeZone;

readonly class ProcessPaymentAction
{
    public function __construct(
        private string $processorUrl,
        private HttpClientService $httpClient
    ) {}

    public function execute(array $paymentData): array
    {
        // Make HTTP request using generic client
        $response = $this->httpClient->post($this->processorUrl, [
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

        // Handle payment-specific response logic
        return $this->handlePaymentResponse($response, $paymentData['correlationId']);
    }

    private function handlePaymentResponse(array $response, string $correlationId): array
    {
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
            
            return [
                'success' => true,
                'correlationId' => $correlationId,
                'transactionId' => $responseData['transactionId'] ?? uniqid('tx_'),
                'status' => 'approved',
                'response' => $responseData
            ];
        }

        // Check for duplicate correlationId error
        if ($statusCode >= 400 && strpos($body, 'CorrelationId already exists') !== false) {
            return [
                'success' => true,
                'correlationId' => $correlationId,
                'transactionId' => 'duplicate_' . uniqid(),
                'status' => 'already_processed',
                'message' => 'Payment already processed with this correlationId'
            ];
        }

        return [
            'success' => false,
            'correlationId' => $correlationId,
            'error' => "HTTP {$statusCode}: Payment processor returned error",
            'status' => 'failed',
            'http_code' => $statusCode,
            'response_body' => $body
        ];
    }
}