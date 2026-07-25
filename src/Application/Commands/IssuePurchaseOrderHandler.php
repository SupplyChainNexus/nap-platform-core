<?php

declare(strict_types=1);

namespace NAP\Application\Commands;

use NAP\Domain\Events\PurchaseOrderIssued;
use NAP\Domain\Model\SupplierQuote;

final class IssuePurchaseOrderHandler
{
    /**
     * Evaluates a supplier quote and generates a PO event if eligible.
     *
     * @param SupplierQuote $quote
     * @param float $minSavingsThreshold
     * @return PurchaseOrderIssued|null Returns event if issued, null if ineligible
     */
    public function handle(SupplierQuote $quote, float $minSavingsThreshold = 50.0): ?PurchaseOrderIssued
    {
        if (!$quote->isEligibleForAutoPo($minSavingsThreshold)) {
            return null;
        }

        $poId = "PO-" . strtoupper(bin2hex(random_bytes(4)));
        $savings = $quote->calculateSavings();

        return new PurchaseOrderIssued(
            $quote->getCaseId(),
            [
                "poId" => $poId,
                "quoteId" => $quote->getQuoteId(),
                "supplierId" => $quote->getSupplierId(),
                "caseId" => $quote->getCaseId(),
                "totalAmount" => $quote->getQuotedAmount(),
                "savingsAmount" => $savings
            ]
        );
    }
}