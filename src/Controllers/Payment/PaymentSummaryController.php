<?php

namespace RinhaSlim\App\Controllers\Payment;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PaymentSummaryController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $from = $queryParams['from'] ?? null;
        $to = $queryParams['to'] ?? null;

        $summary = [
            'default' => [
                'totalRequests' => 43236,
                'totalAmount' => 415542345.98
            ],
            'fallback' => [
                'totalRequests' => 423545,
                'totalAmount' => 329347.34
            ]
        ];

        $response->getBody()->write(json_encode($summary));

        return $response
            ->withHeader('Content-Type', 'application/json');
    }
}