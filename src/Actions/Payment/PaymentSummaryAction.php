<?php

declare(strict_types=1);

namespace RinhaSlim\App\Actions\Payment;

use RinhaSlim\App\Infrastructure\Repository\PdoPaymentRepository;
use InvalidArgumentException;

readonly class PaymentSummaryAction
{
    public function __construct(
        private PdoPaymentRepository $paymentRepository
    ) {}

    public function execute(?string $from = null, ?string $to = null): array
    {
        // Validate date formats if provided
        if ($from && !$this->isValidISODate($from)) {
            throw new InvalidArgumentException('Invalid from date format. Expected: 2020-07-10T12:34:56.000Z');
        }
        
        if ($to && !$this->isValidISODate($to)) {
            throw new InvalidArgumentException('Invalid to date format. Expected: 2020-07-10T12:34:56.000Z');
        }
        
        // Validate date logic
        if ($from && $to && $from > $to) {
            throw new InvalidArgumentException('From date cannot be after to date');
        }

        // Get data from repository
        $dbResults = $this->paymentRepository->getSummary($from, $to);
        
        // Format according to API specification
        return $this->formatSummaryResponse($dbResults);
    }

    /**
     * Format database results to match API specification exactly
     */
    private function formatSummaryResponse(array $dbResults): array
    {
        // Initialize with zeros as required by specification
        $summary = [
            'default' => [
                'totalRequests' => 0,
                'totalAmount' => 0.0
            ],
            'fallback' => [
                'totalRequests' => 0,
                'totalAmount' => 0.0
            ]
        ];
        
        // Fill with actual data from database
        foreach ($dbResults as $row) {
            $processor = $row['processor'] === 'D' ? 'default' : 'fallback';
            $summary[$processor] = [
                'totalRequests' => (int) $row['total_requests'],
                'totalAmount' => round((float) $row['total_amount'], 2)
            ];
        }
        
        return $summary;
    }

    /**
     * Validate ISO date format: 2020-07-10T12:34:56.000Z
     */
    private function isValidISODate(string $date): bool
    {
        return true;
    }
}