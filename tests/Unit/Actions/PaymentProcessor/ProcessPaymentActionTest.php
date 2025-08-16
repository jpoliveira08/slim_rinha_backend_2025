<?php

declare(strict_types=1);

namespace RinhaSlim\Tests\Unit\Actions\PaymentProcessor;

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\ValueObject\Payment;
use RinhaSlim\App\ValueObject\ProcessingResult;
use DateTime;

class ProcessPaymentActionTest extends TestCase
{
    private ProcessPaymentAction $action;
    private $stubHttpClient;

    protected function setUp(): void
    {
        $this->stubHttpClient = $this->createStub(HttpClientInterface::class);
        $this->action = new ProcessPaymentAction($this->stubHttpClient);
    }

    public function testShouldProcessPaymentSuccessfully(): void
    {
        // Arrange
        $payment = new Payment(
            '123e4567-e89b-12d3-a456-426614174000',
            100.50,
            new DateTime('2025-01-01T12:00:00Z')
        );

        $expectedResponse = [
            'message' => 'payment processed successfully'
        ];
        
        $this->stubHttpClient
            ->method('post')
            ->with(
                '/payments',
                [
                    'correlationId' => '123e4567-e89b-12d3-a456-426614174000',
                    'amount' => 100.50,
                    'requestedAt' => '2025-01-01T12:00:00+00:00'
                ]
            )
            ->willReturn($expectedResponse);

        // Act
        $result = $this->action->execute(
            'http://payment-processor-default:8080',
            $payment
        );

        // Assert
        $this->assertInstanceOf(ProcessingResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('payment processed successfully', $result->getMessage());
    }

    public function testShouldHandlePaymentProcessingFailure(): void
    {
        // Arrange
        $payment = new Payment(
            '456e7890-e89b-12d3-a456-426614174001',
            75.25,
            new DateTime('2025-01-01T13:00:00Z')
        );

        $this->stubHttpClient
            ->method('post')
            ->willThrowException(new \RuntimeException('Server error', 500));

        // Act
        $result = $this->action->execute(
            'http://payment-processor-fallback:8080',
            $payment
        );

        // Assert
        $this->assertInstanceOf(ProcessingResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Server error', $result->getMessage());
    }

    public function testShouldHandleTimeoutError(): void
    {
        // Arrange
        $payment = new Payment(
            '789e1234-e89b-12d3-a456-426614174002',
            200.00,
            new DateTime('2025-01-01T14:00:00Z')
        );

        $this->stubHttpClient
            ->method('post')
            ->willThrowException(new \RuntimeException('Request timeout', 408));

        // Act
        $result = $this->action->execute(
            'http://payment-processor-default:8080',
            $payment
        );

        // Assert
        $this->assertInstanceOf(ProcessingResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Request timeout', $result->getMessage());
    }

    public function testShouldValidatePaymentDataBeforeSending(): void
    {
        // Arrange
        $payment = new Payment(
            '123e4567-e89b-12d3-a456-426614174000',
            50.75,
            new DateTime('2025-01-01T15:00:00Z')
        );

        $this->stubHttpClient
            ->method('post')
            ->with(
                $this->equalTo('/payments'),
                $this->callback(function($data) {
                    return isset($data['correlationId']) && 
                           isset($data['amount']) && 
                           isset($data['requestedAt']) &&
                           $data['correlationId'] === '123e4567-e89b-12d3-a456-426614174000' &&
                           $data['amount'] === 50.75;
                })
            )
            ->willReturn(['message' => 'success']);

        // Act
        $result = $this->action->execute(
            'http://payment-processor-default:8080',
            $payment
        );

        // Assert
        $this->assertTrue($result->isSuccess());
    }
}
