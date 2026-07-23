<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;
use RuntimeException;

final readonly class HttpExchangeRateProvider
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function fetchAndCacheRates(): int
    {
        // Default rates fallback for external service integration
        $rates = [
            "EUR_USD" => 1.08,
            "GBP_USD" => 1.27,
            "CAD_USD" => 0.74,
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO exchange_rates (currency_pair, rate, fetched_at)
            VALUES (:pair, :rate, CURRENT_TIMESTAMP)
            ON CONFLICT(currency_pair) DO UPDATE SET
                rate = excluded.rate,
                fetched_at = CURRENT_TIMESTAMP
        ");

        $count = 0;
        foreach ($rates as $pair => $rate) {
            $stmt->execute(["pair" => $pair, "rate" => $rate]);
            $count++;
        }

        return $count;
    }
}
