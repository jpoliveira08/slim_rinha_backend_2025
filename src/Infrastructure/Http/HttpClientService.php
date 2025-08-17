<?php

declare(strict_types=1);

namespace RinhaSlim\App\Infrastructure\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

readonly class HttpClientService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
            'http_errors' => false,
        ]);
    }

    /**
     * Generic HTTP request method
     */
    public function request(string $method, string $url, array $options = []): array
    {
        try {
            $response = $this->client->request($method, $url, $options);
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $response->getBody()->getContents(),
                'response' => $response
            ];

        } catch (ConnectException $e) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
                'error_type' => 'connection'
            ];
            
        } catch (RequestException $e) {
            return [
                'success' => false,
                'error' => 'Request failed: ' . $e->getMessage(),
                'error_type' => 'request'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Unexpected error: ' . $e->getMessage(),
                'error_type' => 'unexpected'
            ];
        }
    }

    /**
     * Convenience methods for common HTTP verbs
     */
    public function get(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): array
    {
        return $this->request('POST', $url, $options);
    }

    public function put(string $url, array $options = []): array
    {
        return $this->request('PUT', $url, $options);
    }

    public function delete(string $url, array $options = []): array
    {
        return $this->request('DELETE', $url, $options);
    }
}