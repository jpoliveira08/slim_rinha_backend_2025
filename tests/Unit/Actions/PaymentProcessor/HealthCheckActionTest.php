<?php

declare(strict_types=1);

namespace RinhaSlim\Tests\Unit\Actions\PaymentProcessor;

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Actions\PaymentProcessor\HealthCheckAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\ValueObject\HealthCheck;

class HealthCheckActionTest extends TestCase
{
    private HealthCheckAction $action;
    private $stubHttpClient;

    protected function setUp(): void
    {
        $this->stubHttpClient = $this->createStub(HttpClientInterface::class);
        $this->action = new HealthCheckAction($this->stubHttpClient);
    }

    public function testShouldReturnHealthCheckWhenServiceIsHealthy(): void
    {
        // Arrange
        $expectedResponse = [
            'failing' => false,
            'minResponseTime' => 100
        ];
        
        $this->stubHttpClient
            ->method('get')
            ->with('/payments/service-health')
            ->willReturn($expectedResponse);

        // Act
        $healthCheck = $this->action->execute('http://payment-processor-default:8080');

        // Assert
        $this->assertInstanceOf(HealthCheck::class, $healthCheck);
        $this->assertFalse($healthCheck->isFailing());
        $this->assertEquals(100, $healthCheck->getMinResponseTime());
    }

    public function testShouldReturnHealthCheckWhenServiceIsFailing(): void
    {
        // Arrange
        $expectedResponse = [
            'failing' => true,
            'minResponseTime' => 5000
        ];
        
        $this->stubHttpClient
            ->method('get')
            ->with('/payments/service-health')
            ->willReturn($expectedResponse);

        // Act
        $healthCheck = $this->action->execute('http://payment-processor-fallback:8080');

        // Assert
        $this->assertInstanceOf(HealthCheck::class, $healthCheck);
        $this->assertTrue($healthCheck->isFailing());
        $this->assertEquals(5000, $healthCheck->getMinResponseTime());
    }

    public function testShouldThrowExceptionWhenHttpClientFails(): void
    {
        // Arrange
        $this->stubHttpClient
            ->method('get')
            ->willThrowException(new \RuntimeException('HTTP request failed'));

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP request failed');

        // Act
        $this->action->execute('http://payment-processor-default:8080');
    }
}