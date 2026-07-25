<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Domain\ValueObjects\SupplierQuote;

final class AutomatedPriceBenchmarker
{
    /**
     * Evaluates supplier quotes against baseline assessment prices and selects optimal supplier.
     *
     * @param array<int, array<string, mixed>> $baselineParts
     * @param array<int, SupplierQuote> $quotes
     * @return array<string, mixed>
     */
    public function benchmark(array $baselineParts, array $quotes): array
    {
        $totalBaselinePartsCost = 0.0;
        foreach ($baselineParts as $part) {
            $price = is_numeric($part["priceExclVat"] ?? null) ? (float) $part["priceExclVat"] : 0.0;
            $totalBaselinePartsCost += $price;
        }

        /** @var array<string, array<int, SupplierQuote>> $quotesByPart */
        $quotesByPart = [];
        foreach ($quotes as $quote) {
            $quotesByPart[$quote->getPartNumber()][] = $quote;
        }

        $totalBenchmarkedCost = 0.0;
        $selectedQuotes = [];

        foreach ($baselineParts as $part) {
            $partNumber = is_string($part["partNumber"] ?? null) ? $part["partNumber"] : "";
            $baselinePrice = is_numeric($part["priceExclVat"] ?? null) ? (float) $part["priceExclVat"] : 0.0;

            $partQuotes = $quotesByPart[$partNumber] ?? [];

            if (empty($partQuotes)) {
                $totalBenchmarkedCost += $baselinePrice;
                continue;
            }

            // Find lowest price quote
            usort($partQuotes, fn(SupplierQuote $a, SupplierQuote $b) => $a->getUnitPriceExclVat() <=> $b->getUnitPriceExclVat());
            $bestQuote = $partQuotes[0];

            $totalBenchmarkedCost += $bestQuote->getUnitPriceExclVat();
            $selectedQuotes[] = $bestQuote->toArray();
        }

        $savingsAmount = max(0.0, $totalBaselinePartsCost - $totalBenchmarkedCost);
        $savingsPercentage = $totalBaselinePartsCost > 0.0 ? round(($savingsAmount / $totalBaselinePartsCost) * 100, 2) : 0.0;

        return [
            "baselinePartsTotalExclVat" => $totalBaselinePartsCost,
            "benchmarkedPartsTotalExclVat" => $totalBenchmarkedCost,
            "totalSavingsAmount" => $savingsAmount,
            "savingsPercentage" => $savingsPercentage,
            "selectedSupplierQuotes" => $selectedQuotes
        ];
    }
}
