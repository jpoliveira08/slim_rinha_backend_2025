<?php

declare(strict_types=1);

namespace RinhaSlim\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\ValueObject\Payment;
use DateTime;

class PaymentTest extends TestCase
{
    public function testShouldCreatePayment(): void
    {
        $correlationId = '4a7901b8-7d26-4d9d-aa19-4dc1c7cf60b3';
        $amount = 19.90;

        $requestedAt = new DateTime();

        $payment = new Payment($correlationId, $amount, $requestedAt);

        $this->assertEquals($correlationId, $payment->getCorrelationId());
        $this->assertEquals($amount, $payment->getAmount());
        $this->assertEquals($requestedAt, $payment->getRequestedAt());
    }
}