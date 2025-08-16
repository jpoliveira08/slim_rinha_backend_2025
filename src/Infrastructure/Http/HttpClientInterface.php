<?php

declare(strict_types=1);

namespace RinhaSlim\App\Infrastructure\Http;

interface HttpClientInterface
{
    /**
     * Perform a GET request
     *
     * @param string $endpoint
     * @param array $headers
     * @return array
     * @throws \RuntimeException
     */
    public function get(string $endpoint, array $headers = []): array;

    /**
     * Perform a POST request
     *
     * @param string $endpoint
     * @param array $data
     * @param array $headers
     * @return array
     * @throws \RuntimeException
     */
    public function post(string $endpoint, array $data = [], array $headers = []): array;

    /**
     * Set the base URL for requests
     *
     * @param string $baseUrl
     * @return void
     */
    public function setBaseUrl(string $baseUrl): void;

    /**
     * Set timeout for requests
     *
     * @param int $timeoutSeconds
     * @return void
     */
    public function setTimeout(int $timeoutSeconds): void;
}
