<?php
// filepath: /home/jpoliveira08/projetos/rinha_backend/2025/slim/src/Controllers/Payment/PaymentSummaryController.php

declare(strict_types=1);

namespace RinhaSlim\App\Controllers\Payment;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RinhaSlim\App\Actions\Payment\PaymentSummaryAction;

readonly class PaymentSummaryController
{
    public function __construct(
        private PaymentSummaryAction $paymentSummaryAction
    ) {}

    public function __invoke(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $from = $queryParams['from'] ?? null;
            $to = $queryParams['to'] ?? null;
            
            $summary = $this->paymentSummaryAction->execute($from, $to);
            
            $response->getBody()->write(json_encode($summary));

            return $response->withHeader('Content-Type', 'application/json'); 
        } catch (\InvalidArgumentException $e) {
            $error = ['error' => $e->getMessage()];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            $error = ['error' => 'Internal server error'];
            $response->getBody()->write(json_encode($error));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}