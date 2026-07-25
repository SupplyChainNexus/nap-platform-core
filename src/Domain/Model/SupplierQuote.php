<?php

declare(strict_types=1);

namespace NAP\Domain\Model;

final class SupplierQuote
{
    private string $quoteId;
    private string $supplierId;
    private string $caseId;
    private float $quotedAmount;
    private float $benchmarkPrice;

    public function __construct(
        string $quoteId,
        string $supplierId,
        string $caseId,
        float $quotedAmount,
        float $benchmarkPrice
    ) {
        if ($quotedAmount <= 0) {
            throw new \InvalidArgumentException("Quoted amount must be greater than zero.");
        }

        if ($benchmarkPrice <= 0) {
            throw new \InvalidArgumentException("Benchmark price must be greater than zero.");
        }

        $this->quoteId = $quoteId;
        $this->supplierId = $supplierId;
        $this->caseId = $caseId;
        $this->quotedAmount = $quotedAmount;
        $this->benchmarkPrice = $benchmarkPrice;
    }

    public function getQuoteId(): string
    {
        return $this->quoteId;
    }

    public function getSupplierId(): string
    {
        return $this->supplierId;
    }

    public function getCaseId(): string
    {
        return $this->caseId;
    }

    public function getQuotedAmount(): float
    {
        return $this->quotedAmount;
    }

    public function getBenchmarkPrice(): float
    {
        return $this->benchmarkPrice;
    }

    public function calculateSavings(): float
    {
        return max(0.0, $this->benchmarkPrice - $this->quotedAmount);
    }

    public function isEligibleForAutoPo(float $minSavingsThreshold = 50.0): bool
    {
        return $this->calculateSavings() >= $minSavingsThreshold;
    }
}