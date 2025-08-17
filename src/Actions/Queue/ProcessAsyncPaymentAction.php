<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\Queue;

use Predis\Client as RedisClient;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessPaymentAction;
use RinhaSlim\App\Actions\PaymentProcessor\ProcessFallbackPaymentAction;

readonly class ProcessAsyncPaymentAction
{
    public function __construct(
        private RedisClient $redis,
        private ProcessPaymentAction $processPaymentAction,
        private ProcessFallbackPaymentAction $processFallbackPaymentAction
    ) {}

    public function execute(): void
    {
        while (true) {
            $paymentData = $this->redis->brpop(['payment_queue'], 1);
            
            if ($paymentData) {
                $payment = json_decode($paymentData[1], true);
                
                if ($payment) {
                    $this->processPayment($payment);
                }
            }
        }
    }

    private function processPayment(array $paymentData): void
    {
        try {
            // Try main processor first
            $result = $this->processPaymentAction->execute($paymentData);
            
            // If main processor fails, try fallback
            if (!$result['success']) {
                $fallbackResult = $this->processFallbackPaymentAction->execute($paymentData);
                
                // Store the result for the client to retrieve
                $this->redis->setex(
                    "payment_result:{$paymentData['correlationId']}", 
                    3600, 
                    json_encode($fallbackResult)
                );
            } else {
                // Store the successful result
                $this->redis->setex(
                    "payment_result:{$paymentData['correlationId']}", 
                    3600, 
                    json_encode($result)
                );
            }
            
        } catch (\Exception $e) {
            // Log error and store failed result
            error_log("Async payment processing failed for {$paymentData['correlationId']}: " . $e->getMessage());
            
            $errorResult = [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'status' => 'failed',
                'error' => 'Internal processing error'
            ];
            
            $this->redis->setex(
                "payment_result:{$paymentData['correlationId']}", 
                3600, 
                json_encode($errorResult)
            );
        }
    }
}