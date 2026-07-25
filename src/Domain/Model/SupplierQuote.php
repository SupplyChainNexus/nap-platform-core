<?php

declare(strict_types=1);

namespace NAP\Domain\Model;

final class SupplierQuote
{
    private string $quoteId;
    private string $supplierId;
    private string $caseId;

    /** @var array<int, QuoteLineItem> */
    private array $lineItems;

    /**
     * @param string $quoteId
     * @param string $supplierId
     * @param string $caseId
     * @param array<int, QuoteLineItem> $lineItems
     */
    public function __construct(
        string $quoteId,
        string $supplierId,
        string $caseId,
        array $lineItems
    ) {
        if (count($lineItems) === 0) {
            throw new \InvalidArgumentException("Supplier quote must contain at least one valid line item.");
        }

        $this->quoteId = $quoteId;
        $this->supplierId = $supplierId;
        $this->caseId = $caseId;
        $this->lineItems = array_values($lineItems);
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

    /**
     * @return array<int, QuoteLineItem>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function getQuotedAmount(): float
    {
        $total = 0.0;
        foreach ($this->lineItems as $item) {
            $total += $item->getQuotedPrice();
        }
        return $total;
    }

    public function getBenchmarkPrice(): float
    {
        $total = 0.0;
        foreach ($this->lineItems as $item) {
            $total += $item->getBenchmarkOemPrice();
        }
        return $total;
    }

    public function calculateSavings(): float
    {
        $totalSavings = 0.0;
        foreach ($this->lineItems as $item) {
            $totalSavings += $item->calculateSavings();
        }
        return $totalSavings;
    }

    public function isEligibleForAutoPo(float $minSavingsThreshold = 50.0): bool
    {
        return $this->calculateSavings() >= $minSavingsThreshold;
    }
}