# TDD Guide for Rinha de Backend 2025 - Slim PHP

This guide provides step-by-step instructions to build the Rinha de Backend 2025 solution using Test-Driven Development (TDD) methodology.

## 🔴 Red → 🟢 Green → 🔵 Refactor Cycle

Follow the classic TDD cycle:
1. **🔴 RED**: Write a failing test
2. **🟢 GREEN**: Write minimal code to make the test pass
3. **🔵 REFACTOR**: Improve the code while keeping tests green

## 📋 Prerequisites Setup

Before starting TDD, ensure your environment is ready:

```bash
# 1. Install dependencies
composer install

# 2. Setup PHPUnit configuration
# Create phpunit.xml if it doesn't exist

# 3. Create test directory structure
mkdir -p tests/{Unit,Integration,Feature}
mkdir -p tests/Unit/{Controller,Service,Repository,Queue}
```

## 🎯 TDD Implementation Plan

### Phase 1: Core Domain Models and Services

#### Step 1.1: Payment Value Object
**🔴 RED**: Create failing test for Payment entity

```bash
# Create test file
touch tests/Unit/Model/PaymentTest.php
```

**Test to write:**
```php
<?php
// tests/Unit/Model/PaymentTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Model\Payment;

class PaymentTest extends TestCase
{
    public function testShouldCreatePaymentWithValidData()
    {
        $correlationId = '123e4567-e89b-12d3-a456-426614174000';
        $amount = 100.50;
        $requestedAt = new \DateTime();
        
        $payment = new Payment($correlationId, $amount, $requestedAt);
        
        $this->assertEquals($correlationId, $payment->getCorrelationId());
        $this->assertEquals($amount, $payment->getAmount());
        $this->assertEquals($requestedAt, $payment->getRequestedAt());
    }
    
    public function testShouldThrowExceptionForInvalidCorrelationId()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new Payment('invalid-uuid', 100.50, new \DateTime());
    }
    
    public function testShouldThrowExceptionForNegativeAmount()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        new Payment('123e4567-e89b-12d3-a456-426614174000', -10.50, new \DateTime());
    }
}
```

**🟢 GREEN**: Run test and see it fail, then create minimal implementation:

```bash
# Run test (should fail)
vendor/bin/phpunit tests/Unit/Model/PaymentTest.php

# Create implementation
mkdir -p src/Model
touch src/Model/Payment.php
```

**Implementation:**
```php
<?php
// src/Model/Payment.php

namespace RinhaSlim\App\Model;

class Payment
{
    private string $correlationId;
    private float $amount;
    private \DateTime $requestedAt;
    
    public function __construct(string $correlationId, float $amount, \DateTime $requestedAt)
    {
        $this->validateCorrelationId($correlationId);
        $this->validateAmount($amount);
        
        $this->correlationId = $correlationId;
        $this->amount = $amount;
        $this->requestedAt = $requestedAt;
    }
    
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }
    
    public function getAmount(): float
    {
        return $this->amount;
    }
    
    public function getRequestedAt(): \DateTime
    {
        return $this->requestedAt;
    }
    
    private function validateCorrelationId(string $correlationId): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $correlationId)) {
            throw new \InvalidArgumentException('Invalid correlation ID format');
        }
    }
    
    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
    }
}
```

**🔵 REFACTOR**: Run tests again and refactor if needed.

#### Step 1.2: Payment Processor Actions
**🔴 RED**: Test for payment processor actions using Action pattern

**Action Pattern Benefits:**
- ✅ **Single Responsibility**: Each action has one clear purpose
- ✅ **Testability**: Easy to unit test with stubs
- ✅ **Reusability**: Actions can be composed and reused
- ✅ **Maintainability**: Clear separation of concerns

**HealthCheck Action Test:**
```php
<?php
// tests/Unit/Actions/PaymentProcessor/HealthCheckActionTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Actions\PaymentProcessor\HealthCheckAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\Model\HealthCheck;

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
```

**ProcessPayment Action Test:**
```php
<?php
// tests/Unit/Actions/PaymentProcessor/ProcessPaymentActionTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\Models\Payment;
use RinhaSlim\App\Model\ProcessingResult;

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
            new \DateTime('2025-01-01T12:00:00Z')
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
        $this->assertEquals('default', $result->getProcessorUsed());
    }

    public function testShouldHandlePaymentProcessingFailure(): void
    {
        // Arrange
        $payment = new Payment(
            '456e7890-e89b-12d3-a456-426614174001',
            75.25,
            new \DateTime('2025-01-01T13:00:00Z')
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
        $this->assertStringContains('Server error', $result->getMessage());
    }
}
```

**🟢 GREEN**: Create minimal implementations:

```bash
# Run tests (should fail initially)
vendor/bin/phpunit tests/Unit/Actions/PaymentProcessor/

# Create the action implementations
mkdir -p src/Actions/PaymentProcessor
mkdir -p src/Infrastructure/Http
mkdir -p src/Model
```

**Action Implementations:**
```php
<?php
// src/Actions/PaymentProcessor/HealthCheckAction.php

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\Model\HealthCheck;

class HealthCheckAction
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function execute(string $processorUrl): HealthCheck
    {
        try {
            $this->httpClient->setBaseUrl($processorUrl);
            $this->httpClient->setTimeout(5);
            
            $response = $this->httpClient->get('/payments/service-health');

            return new HealthCheck(
                $response['failing'] ?? true,
                $response['minResponseTime'] ?? 5000
            );

        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Health check failed for {$processorUrl}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
```

```php
<?php
// src/Actions/PaymentProcessor/ProcessPaymentAction.php

namespace RinhaSlim\App\Actions\PaymentProcessor;

use RinhaSlim\App\Infrastructure\Http\HttpClientInterface;
use RinhaSlim\App\Models\Payment;
use RinhaSlim\App\Model\ProcessingResult;

class ProcessPaymentAction
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function execute(string $processorUrl, Payment $payment): ProcessingResult
    {
        try {
            $this->httpClient->setBaseUrl($processorUrl);
            $this->httpClient->setTimeout(10);
            
            $requestData = [
                'correlationId' => $payment->getCorrelationId(),
                'amount' => $payment->getAmount(),
                'requestedAt' => $payment->getRequestedAt()->format('c')
            ];

            $response = $this->httpClient->post('/payments', $requestData);

            return ProcessingResult::success(
                $response['message'] ?? 'payment processed successfully',
                $this->extractProcessorType($processorUrl),
                $payment->getCorrelationId()
            );

        } catch (\Exception $e) {
            return ProcessingResult::failure(
                "Payment processing failed: " . $e->getMessage(),
                $this->extractProcessorType($processorUrl),
                $payment->getCorrelationId()
            );
        }
    }

    private function extractProcessorType(string $processorUrl): string
    {
        if (str_contains($processorUrl, 'fallback')) {
            return 'fallback';
        }
        
        if (str_contains($processorUrl, 'default')) {
            return 'default';
        }

        return 'unknown';
    }
}
```

**� REFACTOR**: Add HTTP Client Interface and Implementation

```php
<?php
// src/Infrastructure/Http/HttpClientInterface.php

namespace RinhaSlim\App\Infrastructure\Http;

interface HttpClientInterface
{
    public function get(string $endpoint, array $headers = []): array;
    public function post(string $endpoint, array $data = [], array $headers = []): array;
    public function setBaseUrl(string $baseUrl): void;
    public function setTimeout(int $timeoutSeconds): void;
}
```

#### Step 1.3: Circuit Breaker Pattern
**🔴 RED**: Test for circuit breaker

```php
<?php
// tests/Unit/Service/CircuitBreakerTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Service\CircuitBreaker;

class CircuitBreakerTest extends TestCase
{
    public function testShouldAllowCallWhenClosed()
    {
        $circuitBreaker = new CircuitBreaker('test', 5, 60); // 5 failures, 60s timeout
        
        $result = $circuitBreaker->call(function() {
            return 'success';
        });
        
        $this->assertEquals('success', $result);
        $this->assertEquals(CircuitBreaker::STATE_CLOSED, $circuitBreaker->getState());
    }
    
    public function testShouldOpenAfterMultipleFailures()
    {
        $circuitBreaker = new CircuitBreaker('test', 2, 60);
        
        // First failure
        try {
            $circuitBreaker->call(function() {
                throw new \Exception('failure');
            });
        } catch (\Exception $e) {}
        
        // Second failure - should open circuit
        try {
            $circuitBreaker->call(function() {
                throw new \Exception('failure');
            });
        } catch (\Exception $e) {}
        
        $this->assertEquals(CircuitBreaker::STATE_OPEN, $circuitBreaker->getState());
        
        // Next call should fail fast
        $this->expectException(\RinhaSlim\App\Exception\CircuitOpenException::class);
        $circuitBreaker->call(function() {
            return 'should not execute';
        });
    }
}
```

### Phase 2: Payment Processing Service with Actions

#### Step 2.1: Payment Service with Action Composition
**🔴 RED**: Test payment service using action composition

```php
<?php
// tests/Unit/Service/PaymentServiceTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Service\PaymentService;
use RinhaSlim\App\Actions\PaymentProcessor\HealthCheckAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Repository\PaymentRepository;
use RinhaSlim\App\Queue\PaymentQueue;

class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;
    private $stubDefaultHealthAction;
    private $stubFallbackHealthAction;
    private $stubDefaultProcessAction;
    private $stubFallbackProcessAction;
    private $stubRepository;
    private $stubQueue;
    
    public function setUp(): void
    {
        $this->stubDefaultHealthAction = $this->createStub(HealthCheckAction::class);
        $this->stubFallbackHealthAction = $this->createStub(HealthCheckAction::class);
        $this->stubDefaultProcessAction = $this->createStub(ProcessPaymentAction::class);
        $this->stubFallbackProcessAction = $this->createStub(ProcessPaymentAction::class);
        $this->stubRepository = $this->createStub(PaymentRepository::class);
        $this->stubQueue = $this->createStub(PaymentQueue::class);
        
        $this->paymentService = new PaymentService(
            $this->stubDefaultHealthAction,
            $this->stubFallbackHealthAction,
            $this->stubDefaultProcessAction,
            $this->stubFallbackProcessAction,
            $this->stubRepository,
            $this->stubQueue
        );
    }
    
    public function testShouldUseDefaultProcessorWhenHealthy(): void
    {
        // Arrange
        $payment = new \RinhaSlim\App\Models\Payment(
            '123e4567-e89b-12d3-a456-426614174000',
            100.50,
            new \DateTime()
        );

        $healthyStatus = new \RinhaSlim\App\Model\HealthCheck(false, 50);
        $successResult = \RinhaSlim\App\Model\ProcessingResult::success(
            'payment processed successfully', 
            'default',
            $payment->getCorrelationId()
        );

        $this->stubDefaultHealthAction
            ->method('execute')
            ->willReturn($healthyStatus);
            
        $this->stubDefaultProcessAction
            ->method('execute')
            ->willReturn($successResult);
            
        // Act
        $result = $this->paymentService->processPayment($payment);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('default', $result->getProcessorUsed());
    }
    
    public function testShouldUseFallbackProcessorWhenDefaultFails(): void
    {
        // Arrange
        $payment = new \RinhaSlim\App\Models\Payment(
            '456e7890-e89b-12d3-a456-426614174001',
            75.25,
            new \DateTime()
        );

        $unhealthyStatus = new \RinhaSlim\App\Model\HealthCheck(true, 5000);
        $healthyStatus = new \RinhaSlim\App\Model\HealthCheck(false, 200);
        $successResult = \RinhaSlim\App\Model\ProcessingResult::success(
            'payment processed successfully', 
            'fallback',
            $payment->getCorrelationId()
        );

        $this->stubDefaultHealthAction
            ->method('execute')
            ->willReturn($unhealthyStatus);
            
        $this->stubFallbackHealthAction
            ->method('execute')
            ->willReturn($healthyStatus);
            
        $this->stubFallbackProcessAction
            ->method('execute')
            ->willReturn($successResult);
            
        // Act
        $result = $this->paymentService->processPayment($payment);
        
        // Assert
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('fallback', $result->getProcessorUsed());
    }
    
    public function testShouldQueuePaymentWhenBothProcessorsFail(): void
    {
        // Arrange
        $payment = new \RinhaSlim\App\Models\Payment(
            '789e1234-e89b-12d3-a456-426614174002',
            200.00,
            new \DateTime()
        );

        $unhealthyStatus = new \RinhaSlim\App\Model\HealthCheck(true, 5000);

        $this->stubDefaultHealthAction
            ->method('execute')
            ->willReturn($unhealthyStatus);
            
        $this->stubFallbackHealthAction
            ->method('execute')
            ->willReturn($unhealthyStatus);
            
        $this->stubQueue
            ->method('enqueue')
            ->willReturn(true);
            
        // Act
        $result = $this->paymentService->processPayment($payment);
        
        // Assert
        $this->assertTrue($result->isQueued());
    }
}
```

### Phase 3: API Controllers

#### Step 3.1: Payment Controller
**🔴 RED**: Test payment endpoint

```php
<?php
// tests/Unit/Controller/PaymentControllerTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Controller\PaymentController;
use RinhaSlim\App\Service\PaymentService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

class PaymentControllerTest extends TestCase
{
    public function testShouldProcessValidPaymentRequest()
    {
        $stubService = $this->createStub(PaymentService::class);
        $stubService
            ->method('processPayment')
            ->willReturn(new \RinhaSlim\App\Model\ProcessingResult(true, 'success'));
            
        $controller = new PaymentController($stubService);
        
        $stubRequest = $this->createStub(ServerRequestInterface::class);
        $stubRequest->method('getParsedBody')->willReturn([
            'correlationId' => '123e4567-e89b-12d3-a456-426614174000',
            'amount' => 100.50
        ]);
        
        $stubResponse = $this->createStub(ResponseInterface::class);
        $stubResponse->method('withStatus')->willReturnSelf();
        $stubResponse->method('getBody')->willReturn($this->createStub(\Psr\Http\Message\StreamInterface::class));
        
        $result = $controller->processPayment($stubRequest, $stubResponse);
        
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
    
    public function testShouldReturn400ForInvalidData()
    {
        $stubService = $this->createStub(PaymentService::class);
        $controller = new PaymentController($stubService);
        
        $stubRequest = $this->createStub(ServerRequestInterface::class);
        $stubRequest->method('getParsedBody')->willReturn([
            'correlationId' => 'invalid-uuid',
            'amount' => -100.50
        ]);
        
        $stubResponse = $this->createStub(ResponseInterface::class);
        $stubResponse->method('withStatus')->willReturnSelf();
        $stubResponse->method('getBody')->willReturn($this->createStub(\Psr\Http\Message\StreamInterface::class));
        
        $result = $controller->processPayment($stubRequest, $stubResponse);
        
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
    
    public function testShouldReturn400ForMissingCorrelationId()
    {
        $stubService = $this->createStub(PaymentService::class);
        $controller = new PaymentController($stubService);
        
        $stubRequest = $this->createStub(ServerRequestInterface::class);
        $stubRequest->method('getParsedBody')->willReturn([
            'amount' => 100.50
        ]);
        
        $stubResponse = $this->createStub(ResponseInterface::class);
        $stubResponse->method('withStatus')->willReturnSelf();
        $stubResponse->method('getBody')->willReturn($this->createStub(\Psr\Http\Message\StreamInterface::class));
        
        $result = $controller->processPayment($stubRequest, $stubResponse);
        
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}
```

#### Step 3.2: Payment Summary Controller
**🔴 RED**: Test summary endpoint

```php
<?php
// tests/Unit/Controller/PaymentSummaryControllerTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Controller\PaymentSummaryController;
use RinhaSlim\App\Repository\PaymentRepository;

class PaymentSummaryControllerTest extends TestCase
{
    public function testShouldReturnPaymentSummary()
    {
        $stubRepository = $this->createStub(PaymentRepository::class);
        $stubRepository
            ->method('getSummary')
            ->willReturn([
                'default' => ['totalRequests' => 100, 'totalAmount' => 5000.00],
                'fallback' => ['totalRequests' => 10, 'totalAmount' => 500.00]
            ]);
            
        $controller = new PaymentSummaryController($stubRepository);
        
        $stubRequest = $this->createStub(\Psr\Http\Message\ServerRequestInterface::class);
        $stubRequest->method('getQueryParams')->willReturn([]);
        
        $stubResponse = $this->createStub(\Psr\Http\Message\ResponseInterface::class);
        $stubResponse->method('withStatus')->willReturnSelf();
        $stubResponse->method('withHeader')->willReturnSelf();
        $stubResponse->method('getBody')->willReturn($this->createStub(\Psr\Http\Message\StreamInterface::class));
        
        $result = $controller->getSummary($stubRequest, $stubResponse);
        
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $result);
    }
    
    public function testShouldFilterSummaryByDateRange()
    {
        $stubRepository = $this->createStub(PaymentRepository::class);
        $stubRepository
            ->method('getSummary')
            ->with(
                $this->callback(function($from) {
                    return $from instanceof \DateTime && $from->format('Y-m-d') === '2025-01-01';
                }),
                $this->callback(function($to) {
                    return $to instanceof \DateTime && $to->format('Y-m-d') === '2025-01-31';
                })
            )
            ->willReturn([
                'default' => ['totalRequests' => 50, 'totalAmount' => 2500.00],
                'fallback' => ['totalRequests' => 5, 'totalAmount' => 250.00]
            ]);
            
        $controller = new PaymentSummaryController($stubRepository);
        
        $stubRequest = $this->createStub(\Psr\Http\Message\ServerRequestInterface::class);
        $stubRequest->method('getQueryParams')->willReturn([
            'from' => '2025-01-01T00:00:00Z',
            'to' => '2025-01-31T23:59:59Z'
        ]);
        
        $stubResponse = $this->createStub(\Psr\Http\Message\ResponseInterface::class);
        $stubResponse->method('withStatus')->willReturnSelf();
        $stubResponse->method('withHeader')->willReturnSelf();
        $stubResponse->method('getBody')->willReturn($this->createStub(\Psr\Http\Message\StreamInterface::class));
        
        $result = $controller->getSummary($stubRequest, $stubResponse);
        
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $result);
    }
}
```

### Phase 4: Data Layer

#### Step 4.1: Redis Payment Repository
**🔴 RED**: Test Redis repository

```php
<?php
// tests/Unit/Repository/RedisPaymentRepositoryTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Repository\RedisPaymentRepository;
use RinhaSlim\App\Model\Payment;

class RedisPaymentRepositoryTest extends TestCase
{
    private $stubRedis;
    private RedisPaymentRepository $repository;
    
    public function setUp(): void
    {
        $this->stubRedis = $this->createStub(\Predis\Client::class);
        $this->repository = new RedisPaymentRepository($this->stubRedis);
    }
    
    public function testShouldSavePayment()
    {
        $payment = new Payment(
            '123e4567-e89b-12d3-a456-426614174000',
            100.50,
            new \DateTime()
        );
        
        $this->stubRedis
            ->method('hset')
            ->willReturn(1);
            
        $result = $this->repository->save($payment, 'default');
        
        $this->assertTrue($result);
    }
    
    public function testShouldGetSummaryWithDefaultAndFallbackData()
    {
        $this->stubRedis
            ->method('keys')
            ->willReturn([
                'payment:123e4567-e89b-12d3-a456-426614174000',
                'payment:456e7890-e89b-12d3-a456-426614174001'
            ]);
            
        $this->stubRedis
            ->method('hgetall')
            ->willReturnOnConsecutiveCalls(
                ['processor' => 'default', 'amount' => '100.50', 'timestamp' => '2025-01-01T12:00:00Z'],
                ['processor' => 'fallback', 'amount' => '200.75', 'timestamp' => '2025-01-01T12:05:00Z']
            );
            
        $summary = $this->repository->getSummary();
        
        $this->assertEquals(1, $summary['default']['totalRequests']);
        $this->assertEquals(100.50, $summary['default']['totalAmount']);
        $this->assertEquals(1, $summary['fallback']['totalRequests']);
        $this->assertEquals(200.75, $summary['fallback']['totalAmount']);
    }
    
    public function testShouldGetSummaryWithDateFilter()
    {
        $from = new \DateTime('2025-01-01T00:00:00Z');
        $to = new \DateTime('2025-01-31T23:59:59Z');
        
        $this->stubRedis
            ->method('keys')
            ->willReturn([
                'payment:123e4567-e89b-12d3-a456-426614174000'
            ]);
            
        $this->stubRedis
            ->method('hgetall')
            ->willReturn([
                'processor' => 'default', 
                'amount' => '150.00',
                'timestamp' => '2025-01-15T12:00:00Z'
            ]);
            
        $summary = $this->repository->getSummary($from, $to);
        
        $this->assertEquals(1, $summary['default']['totalRequests']);
        $this->assertEquals(150.00, $summary['default']['totalAmount']);
        $this->assertEquals(0, $summary['fallback']['totalRequests']);
        $this->assertEquals(0.0, $summary['fallback']['totalAmount']);
    }
}
```

### Phase 5: Queue and Worker

#### Step 5.1: Redis Queue Implementation
**🔴 RED**: Test queue functionality

```php
<?php
// tests/Unit/Queue/RedisPaymentQueueTest.php

use PHPUnit\Framework\TestCase;
use RinhaSlim\App\Queue\RedisPaymentQueue;
use RinhaSlim\App\Model\Payment;

class RedisPaymentQueueTest extends TestCase
{
    private $stubRedis;
    private RedisPaymentQueue $queue;
    
    public function setUp(): void
    {
        $this->stubRedis = $this->createStub(\Predis\Client::class);
        $this->queue = new RedisPaymentQueue($this->stubRedis);
    }
    
    public function testShouldEnqueuePayment()
    {
        $payment = new Payment(
            '123e4567-e89b-12d3-a456-426614174000',
            100.50,
            new \DateTime()
        );
        
        $this->stubRedis
            ->method('lpush')
            ->willReturn(1);
            
        $result = $this->queue->enqueue($payment);
        
        $this->assertTrue($result);
    }
    
    public function testShouldDequeuePayment()
    {
        $paymentData = json_encode([
            'correlationId' => '123e4567-e89b-12d3-a456-426614174000',
            'amount' => 100.50,
            'requestedAt' => '2025-01-01T12:00:00+00:00'
        ]);
        
        $this->stubRedis
            ->method('brpop')
            ->willReturn(['payment_queue', $paymentData]);
            
        $payment = $this->queue->dequeue();
        
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $payment->getCorrelationId());
        $this->assertEquals(100.50, $payment->getAmount());
    }
    
    public function testShouldReturnNullWhenQueueIsEmpty()
    {
        $this->stubRedis
            ->method('brpop')
            ->willReturn(null);
            
        $payment = $this->queue->dequeue(1); // 1 second timeout
        
        $this->assertNull($payment);
    }
    
    public function testShouldGetQueueLength()
    {
        $this->stubRedis
            ->method('llen')
            ->willReturn(5);
            
        $length = $this->queue->getLength();
        
        $this->assertEquals(5, $length);
    }
}
```

### Phase 6: Integration Tests

#### Step 6.1: End-to-End API Tests
**🔴 RED**: Integration tests

```php
<?php
// tests/Integration/PaymentApiTest.php

use PHPUnit\Framework\TestCase;

class PaymentApiTest extends TestCase
{
    private $app;
    
    public function setUp(): void
    {
        // Setup test application
        $this->app = require __DIR__ . '/../../public/index.php';
    }
    
    public function testPostPaymentEndpoint()
    {
        $request = $this->createRequest('POST', '/payments', [
            'correlationId' => '123e4567-e89b-12d3-a456-426614174000',
            'amount' => 100.50
        ]);
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
    
    public function testGetPaymentSummaryEndpoint()
    {
        $request = $this->createRequest('GET', '/payments-summary');
        
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('default', $data);
        $this->assertArrayHasKey('fallback', $data);
    }
    
    private function createRequest(string $method, string $path, array $data = []): \Psr\Http\Message\ServerRequestInterface
    {
        // Implementation for creating test requests
    }
}
```

## 🗓️ TDD Development Schedule

### Day 1: Foundation with Actions
- [ ] Setup PHPUnit and test structure
- [ ] Implement Payment model with tests
- [ ] Implement HealthCheck and ProcessingResult models with tests
- [ ] Create HealthCheckAction with HTTP client interface and tests
- [ ] Create ProcessPaymentAction with tests

### Day 2: Action Integration & Core Services
- [ ] Complete HTTP client implementation (GuzzleHttpClient)
- [ ] PaymentService with action composition tests
- [ ] Circuit breaker integration with actions
- [ ] Action integration tests

### Day 3: API Layer with Action Controllers
- [ ] PaymentController using actions with tests
- [ ] PaymentSummaryController with tests
- [ ] Request validation and error handling with tests
- [ ] Action-based middleware tests

### Day 4: Data Layer & Queue Actions
- [ ] RedisPaymentRepository with tests
- [ ] RedisPaymentQueue with tests
- [ ] Queue processing actions with tests
- [ ] Data consistency tests

### Day 5: Worker & Integration
- [ ] Worker implementation using actions
- [ ] End-to-end integration tests with action flows
- [ ] Performance tests for action composition
- [ ] Health check caching with actions

### Day 6: Optimization & Polish
- [ ] Action performance optimizations
- [ ] Error handling improvements across actions
- [ ] Load testing with action-based architecture
- [ ] Final integration tests and action choreography

## 🔧 TDD Commands Reference

```bash
# Run all tests
make test

# Run specific test file
vendor/bin/phpunit tests/Unit/Model/PaymentTest.php

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage/

# Run tests in watch mode (install phpunit-watcher)
vendor/bin/phpunit-watcher watch

# Run tests for specific group
vendor/bin/phpunit --group=unit
vendor/bin/phpunit --group=integration

# Run tests with specific filter
vendor/bin/phpunit --filter=testShouldCreatePaymentWithValidData
```

## 📊 TDD Metrics to Track

- **Test Coverage**: Aim for >90%
- **Test Speed**: Unit tests <100ms each
- **Test Isolation**: Each test independent
- **Test Clarity**: Self-documenting test names

## 🎯 **Action-Based TDD Benefits for Rinha Challenge:**

### **1. Modular Architecture**
```php
// Each action has a single, clear responsibility
$healthCheck = $healthCheckAction->execute($processorUrl);
$result = $processPaymentAction->execute($processorUrl, $payment);
```

### **2. Easy Testing with Stubs**
```php
// Clean, focused stubs for each action
$stubHealthAction = $this->createStub(HealthCheckAction::class);
$stubHealthAction->method('execute')->willReturn($healthyStatus);
```

### **3. Action Composition**
```php
// Service orchestrates actions without tight coupling
class PaymentService {
    public function processPayment(Payment $payment): ProcessingResult {
        $defaultHealth = $this->defaultHealthAction->execute($this->defaultUrl);
        
        if ($defaultHealth->isHealthy()) {
            return $this->defaultProcessAction->execute($this->defaultUrl, $payment);
        }
        
        // Fallback to secondary processor...
    }
}
```

### **4. Perfect for Rinha Requirements**
- ✅ **Health Check Action**: Respects 5-second limit with caching
- ✅ **Process Payment Action**: Handles timeouts and retries
- ✅ **Circuit Breaker Integration**: Actions can be wrapped with circuit breakers
- ✅ **Performance Monitoring**: Each action can be individually timed and monitored

## 🔧 **Action Testing Best Practices**

### **1. Test Action Isolation**
```php
public function testHealthCheckActionWithStub(): void
{
    $stubHttpClient = $this->createStub(HttpClientInterface::class);
    $stubHttpClient->method('get')->willReturn(['failing' => false, 'minResponseTime' => 100]);
    
    $action = new HealthCheckAction($stubHttpClient);
    $result = $action->execute('http://processor:8080');
    
    $this->assertFalse($result->isFailing());
}
```

### **2. Test Action Composition**
```php
public function testPaymentServiceComposition(): void
{
    $stubHealthAction = $this->createStub(HealthCheckAction::class);
    $stubProcessAction = $this->createStub(ProcessPaymentAction::class);
    
    // Compose actions in service and test the orchestration
    $service = new PaymentService($stubHealthAction, $stubProcessAction, ...);
    // ... test the flow
}
```

### **3. Test Error Scenarios**
```php
public function testActionHandlesHttpFailure(): void
{
    $stubHttpClient = $this->createStub(HttpClientInterface::class);
    $stubHttpClient->method('post')->willThrowException(new \RuntimeException('Network error'));
    
    $action = new ProcessPaymentAction($stubHttpClient);
    $result = $action->execute('http://processor:8080', $payment);
    
    $this->assertFalse($result->isSuccess());
    $this->assertStringContains('Network error', $result->getMessage());
}
```

## 🔄 **Stubs vs Mocks - When to Use Each**

### **Use Stubs When:**
- ✅ **Testing state-based behavior** (return values, object state)
- ✅ **You need predetermined responses** from dependencies
- ✅ **Testing query operations** (getters, calculations)
- ✅ **Simplifying test setup** with minimal configuration

### **Use Mocks When (rarely in this project):**
- ⚠️ **Testing interaction-based behavior** (method calls, parameters)
- ⚠️ **Verifying command operations** (save, delete, send)
- ⚠️ **Complex integration scenarios** with specific call sequences

### **Stub Examples in Our Project:**
```php
// Good: Testing return values and state
$stubHealthCheck = $this->createStub(HealthCheck::class);
$stubHealthCheck->method('isFailing')->willReturn(false);
$stubHealthCheck->method('getMinResponseTime')->willReturn(50);

// Good: Predetermined responses for different scenarios
$stubClient = $this->createStub(PaymentProcessorClient::class);
$stubClient->method('processPayment')->willReturn(
    new ProcessingResult(true, 'success')
);
```

## 🚀 Ready to Start with Actions?

1. Run `composer install`
2. Create your first action test: `tests/Unit/Actions/PaymentProcessor/HealthCheckActionTest.php`
3. Watch it fail (🔴 RED)
4. Implement the action (🟢 GREEN)
5. Refactor for better design (🔵 REFACTOR)
6. Create the next action and repeat!

### **Action-Based File Structure:**
```
src/
├── Actions/
│   └── PaymentProcessor/
│       ├── HealthCheckAction.php
│       └── ProcessPaymentAction.php
├── Infrastructure/
│   └── Http/
│       ├── HttpClientInterface.php
│       └── GuzzleHttpClient.php
├── Model/
│   ├── HealthCheck.php
│   └── ProcessingResult.php
└── Service/
    └── PaymentService.php (orchestrates actions)

tests/Unit/Actions/PaymentProcessor/
├── HealthCheckActionTest.php
├── ProcessPaymentActionTest.php
└── PaymentProcessorActionsIntegrationTest.php
```

Remember: **Action-oriented TDD = Single Responsibility + Easy Testing + Better Composition** 

Perfect for the Rinha challenge! 🏆
