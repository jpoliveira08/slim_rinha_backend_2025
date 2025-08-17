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
    ) {
    }

    public function execute(): void
    {
        echo "Starting async payment processor...\n";
        
        while (true) {
            // Get payment from queue (blocking with 1 second timeout)
            $queueItem = $this->redis->brpop(['payment_queue'], 1);
            
            if (!$queueItem) {
                continue; // No items in queue, keep polling
            }
            
            $paymentJson = $queueItem[1]; // brpop returns [queue_name, item]
            $paymentData = json_decode($paymentJson, true);
            
            echo "Processing payment: {$paymentData['correlationId']}\n";
            
            $this->processQueuedPayment($paymentData);
        }
    }
    
    private function processQueuedPayment(array $paymentData): void
    {
        // Increment attempts
        $paymentData['attempts'] = ($paymentData['attempts'] ?? 0) + 1;
        
        // Try main processor first
        $result = $this->processPaymentAction->execute($paymentData);
        
        if ($result['success']) {
            echo "✅ Payment {$paymentData['correlationId']} processed successfully with main processor\n";
            $this->storeResult($paymentData['correlationId'], $result);
            return;
        }
        
        // Try fallback processor
        $fallbackResult = $this->processFallbackPaymentAction->execute($paymentData);
        
        if ($fallbackResult['success']) {
            echo "✅ Payment {$paymentData['correlationId']} processed successfully with fallback processor\n";
            $this->storeResult($paymentData['correlationId'], $fallbackResult);
            return;
        }
        
        // Both failed - check retry logic
        if ($paymentData['attempts'] < 3) {
            echo "⚠️ Payment {$paymentData['correlationId']} failed, retrying... (attempt {$paymentData['attempts']})\n";
            // Re-queue for retry
            $this->redis->lpush('payment_queue', json_encode($paymentData));
        } else {
            echo "❌ Payment {$paymentData['correlationId']} failed permanently after 3 attempts\n";
            $this->storeResult($paymentData['correlationId'], [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'status' => 'failed_permanently',
                'error' => 'Payment failed after maximum retry attempts'
            ]);
        }
    }
    
    private function storeResult(string $correlationId, array $result): void
    {
        // Store result in Redis for later retrieval
        $this->redis->setex("payment_result:{$correlationId}", 3600, json_encode($result));
    }
}