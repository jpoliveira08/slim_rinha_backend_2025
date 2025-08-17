<?php

declare(strict_types=1);

namespace RinhaSlim\App\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use DateTimeZone;
use DateTimeImmutable;

readonly class HttpClientService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
            'http_errors' => false // We'll handle errors manually
        ]);
    }

    public function makePaymentRequest(string $url, array $paymentData): array
    {
        try {
            $response = $this->client->post($url, [
                'json' => [
                    'correlationId' => $paymentData['correlationId'],
                    'amount' => $paymentData['amount'],
                    'timestamp' => $paymentData['createdAt'] ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM)
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'RinhaSlim-PaymentProcessor/1.0'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            if ($statusCode >= 200 && $statusCode < 300) {
                // Success response
                $responseData = json_decode($body, true) ?? [];
                
                return [
                    'success' => true,
                    'correlationId' => $paymentData['correlationId'],
                    'transactionId' => $responseData['transactionId'] ?? uniqid('tx_'),
                    'status' => 'approved',
                    'response' => $responseData
                ];
            }

            // HTTP error (4xx, 5xx)
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => "HTTP {$statusCode}: Payment processor returned error",
                'status' => 'failed',
                'http_code' => $statusCode
            ];

        } catch (ConnectException $e) {
            // Network/connection issues
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => 'Payment processor unavailable: ' . $e->getMessage(),
                'status' => 'failed',
                'error_type' => 'connection'
            ];
            
        } catch (RequestException $e) {
            // Other request issues
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => 'Payment request failed: ' . $e->getMessage(),
                'status' => 'failed',
                'error_type' => 'request'
            ];
            
        } catch (\Exception $e) {
            // Unexpected errors
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => 'Unexpected error: ' . $e->getMessage(),
                'status' => 'failed',
                'error_type' => 'unexpected'
            ];
        }
    }
}