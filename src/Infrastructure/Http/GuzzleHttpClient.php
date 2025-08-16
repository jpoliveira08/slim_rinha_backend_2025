<?php

declare(strict_types=1);

namespace RinhaSlim\App\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class GuzzleHttpClient implements HttpClientInterface
{
    private Client $client;
    private string $baseUrl = '';
    private int $timeout = 30;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function get(string $endpoint, array $headers = []): array
    {
        try {
            $response = $this->client->get($this->baseUrl . $endpoint, [
                'headers' => $headers,
                'timeout' => $this->timeout,
                'http_errors' => true
            ]);

            return $this->parseResponse($response);

        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "GET request failed for {$endpoint}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function post(string $endpoint, array $data = [], array $headers = []): array
    {
        try {
            $response = $this->client->post($this->baseUrl . $endpoint, [
                'json' => $data,
                'headers' => array_merge(['Content-Type' => 'application/json'], $headers),
                'timeout' => $this->timeout,
                'http_errors' => true
            ]);

            return $this->parseResponse($response);

        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "POST request failed for {$endpoint}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setTimeout(int $timeoutSeconds): void
    {
        $this->timeout = $timeoutSeconds;
    }

    private function parseResponse(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded ?? [];
    }
}
