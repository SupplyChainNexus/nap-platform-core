<?php

declare(strict_types=1);

namespace NAP\Domain\Contracts;

use NAP\Domain\ValueObjects\PurchaseOrder;

interface SupplierConnectorInterface
{
    public function getSupplierName(): string;

    /**
     * Sends a Request For Quote (RFQ) to an external supplier API.
     *
     * @param string $caseId
     * @param array<int, array<string, mixed>> $parts
     * @return array<string, mixed> Response containing status and quotes
     */
    public function requestQuotes(string $caseId, array $parts): array;

    /**
     * Transmits an issued Purchase Order to an external supplier system.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return bool True if acknowledged successfully by supplier system
     */
    public function dispatchPurchaseOrder(PurchaseOrder $purchaseOrder): bool;
}