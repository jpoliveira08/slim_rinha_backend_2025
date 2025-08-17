<?php

declare(strict_types=1);

namespace RinhaSlim\App\Controllers\Payment;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessFallbackPaymentAction;
use RinhaSlim\App\Actions\Queue\EnqueuePaymentAction;

readonly class PaymentController
{
    public function __construct(
        private ProcessPaymentAction $processPaymentAction,
        private ProcessFallbackPaymentAction $processFallbackPaymentAction,
        private EnqueuePaymentAction $enqueuePaymentAction  // ← Missing this!
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        if (!isset($body['correlationId']) || !isset($body['amount'])) {
            $response->getBody()->write(json_encode(['message' => 'Missing mandatory parameters']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $paymentData = [
            'correlationId' => $body['correlationId'],
            'amount' => $body['amount'],
            'createdAt' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ];

        // Try main processor first
        $result = $this->processPaymentAction->execute($paymentData);

        if ($result['success']) {
            $response->getBody()->write(json_encode([
                'correlationId' => $result['correlationId'],
                'status' => $result['status'],
                'transactionId' => $result['transactionId']
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        // Main failed, try fallback processor
        $fallbackResult = $this->processFallbackPaymentAction->execute($paymentData);

        if ($fallbackResult['success']) {
            $response->getBody()->write(json_encode([
                'correlationId' => $fallbackResult['correlationId'],
                'status' => $fallbackResult['status'],
                'transactionId' => $fallbackResult['transactionId'],
                'processor' => $fallbackResult['processor']
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        // Both processors failed - NOW actually queue it!
        $queueResult = $this->enqueuePaymentAction->execute($paymentData);

        if ($queueResult['success']) {
            $response->getBody()->write(json_encode([
                'correlationId' => $queueResult['correlationId'],
                'status' => 'processing',
                'message' => $queueResult['message']
            ]));
            return $response->withStatus(202)->withHeader('Content-Type', 'application/json');
        }

        // Even queue failed - critical error
        $response->getBody()->write(json_encode([
            'correlationId' => $paymentData['correlationId'],
            'status' => 'error',
            'message' => 'Payment processing temporarily unavailable'
        ]));
        return $response->withStatus(503)->withHeader('Content-Type', 'application/json');
    }
}