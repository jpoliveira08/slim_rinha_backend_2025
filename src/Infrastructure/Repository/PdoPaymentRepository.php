<?php

declare(strict_types=1);

namespace RinhaSlim\App\Infrastructure\Repository;

use PDO;

readonly class PdoPaymentRepository
{
    private PDO $pdo;

    public function __construct(string $dsn, string $username, string $password)
    {
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }

    public function insertPayment(string $correlationId, string $processor, float $amount): bool
    {
        $sql = "INSERT INTO payments (correlation_id, processor, amount) VALUES (?, ?, ?) ON CONFLICT DO NOTHING";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$correlationId, $processor, $amount]);


        return $stmt->rowCount() > 0;
    }

    public function getSummary(?string $from, ?string $to): array
    {
        $sql = "SELECT processor, COUNT(*) as total_requests, SUM(amount) as total_amount FROM payments";
        $params = [];
        
        if ($from || $to) {
            $sql .= " WHERE ";
            $conditions = [];
            
            if ($from) {
                $conditions[] = "processed_at >= ?";
                $params[] = $from;
            }
            if ($to) {
                $conditions[] = "processed_at <= ?";
                $params[] = $to;
            }
            
            $sql .= implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY processor";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}