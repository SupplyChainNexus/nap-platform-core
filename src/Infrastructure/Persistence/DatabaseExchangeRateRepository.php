<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use DateTimeImmutable;
use NAP\Application\Contracts\ExchangeRateProviderInterface;
use InvalidArgumentException;
use PDO;

final readonly class DatabaseExchangeRateRepository implements ExchangeRateProviderInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function getRate(string $fromCurrency, string $toCurrency): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        $stmt = $this->pdo->prepare(
            "SELECT rate FROM exchange_rates WHERE from_currency = :from AND to_currency = :to"
        );
        $stmt->execute(["from" => $from, "to" => $to]);

        $rate = $stmt->fetchColumn();

        if ($rate === false) {
            throw new InvalidArgumentException(sprintf("No exchange rate found for pair %s/%s.", $from, $to));
        }

        return (float) $rate;
    }

    public function saveRate(string $fromCurrency, string $toCurrency, float $rate): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO exchange_rates (from_currency, to_currency, rate, updated_at)
             VALUES (:from, :to, :rate, :updated_at)
             ON CONFLICT(from_currency, to_currency) DO UPDATE SET
                rate = excluded.rate,
                updated_at = excluded.updated_at"
        );

        $stmt->execute([
            "from" => strtoupper($fromCurrency),
            "to" => strtoupper($toCurrency),
            "rate" => $rate,
            "updated_at" => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
