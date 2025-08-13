<?php

declare(strict_types=1);

namespace RinhaSlim\App\Controllers\Payment;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PaymentController
{
    public function __invoke(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody() ?? [];

        if (!isset($body['correlationId']) || !isset($body['amount'])) {
            $response->getBody()->write(json_encode(['message' => 'Missing mandatory parameters']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $payment = [
          'correlationId' => $body['correlationId'],
          'amount' => $body['amount'],
          'createdAt' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
        ];

        return $response
            ->withStatus(202);
    }
}