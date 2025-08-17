<?php
// Create file: src/Actions/Payment/StorePaymentAction.php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\Payment;

use RinhaSlim\App\Infrastructure\Repository\PdoPaymentRepository;

readonly class StorePaymentAction
{
    public function __construct(
        private PdoPaymentRepository $paymentRepository
    ) {}

    /**
     * Store payment result (only successful payments for audit)
     */
    public function execute(array $paymentResult): bool
    {
        // Only store approved payments for audit purposes
        if ($paymentResult['status'] !== 'approved') {
            return true; // Not an error, just not storing failed payments
        }

        // Convert processor name to single character for storage efficiency
        $processor = $this->mapProcessorName($paymentResult['processor'] ?? 'default');
        
        try {
            return $this->paymentRepository->insertPayment(
                $paymentResult['correlationId'],
                $processor,
                (float) $paymentResult['amount']
            );
        } catch (\Exception $e) {
            // Log error but don't crash the payment flow
            error_log("Failed to store payment result for {$paymentResult['correlationId']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Map processor names to single characters for storage
     */
    private function mapProcessorName(string $processor): string
    {
        return match($processor) {
            'fallback' => 'F',
            'default' => 'D',
            default => 'D'
        };
    }
}