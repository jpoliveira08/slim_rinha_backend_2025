<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\Queue;

use Predis\Client as RedisClient;

readonly class EnqueuePaymentAction
{
    public function __construct(private RedisClient $redis)
    {
    }

    public function execute(array $paymentData): array
    {
        try {
            $queueData = [
                ...$paymentData,
                'enqueuedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'attempts' => 0
            ];
            
            $this->redis->lpush('payment_queue', json_encode($queueData));
            
            return [
                'success' => true,
                'correlationId' => $paymentData['correlationId'],
                'message' => 'Payment enqueued for async processing'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'correlationId' => $paymentData['correlationId'],
                'error' => 'Failed to enqueue payment: ' . $e->getMessage()
            ];
        }
    }
}