<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Domain\ValueObjects\PurchaseOrder;

final class PurchaseOrderOrchestrator
{
    /**
     * Groups benchmarked supplier quotes by supplier and generates formal Purchase Orders.
     *
     * @param string $caseId
     * @param array<int, array<string, mixed>> $selectedSupplierQuotes
     * @return array<int, PurchaseOrder>
     */
    public function generatePurchaseOrders(string $caseId, array $selectedSupplierQuotes): array
    {
        /** @var array<string, array<int, array<string, mixed>>> $grouped */
        $grouped = [];

        foreach ($selectedSupplierQuotes as $quote) {
            $supplier = is_string($quote["supplierName"] ?? null) ? $quote["supplierName"] : "OEM_DIRECT";
            $grouped[$supplier][] = $quote;
        }

        $purchaseOrders = [];
        $counter = 1;

        foreach ($grouped as $supplierName => $items) {
            $poNumber = sprintf("PO-%s-%03d", strtoupper(substr(md5($caseId), 0, 6)), $counter++);
            $subtotal = 0.0;

            foreach ($items as $item) {
                $subtotal += is_numeric($item["unitPriceExclVat"] ?? null) ? (float) $item["unitPriceExclVat"] : 0.0;
            }

            $purchaseOrders[] = new PurchaseOrder($poNumber, $caseId, $supplierName, $items, $subtotal);
        }

        return $purchaseOrders;
    }

    /**
     * Calculates final settlement breakdown including labor, parts PO total, VAT, and excess.
     *
     * @param float $laborExclVat
     * @param float $partsExclVat
     * @param float $excessDeduction
     * @param float $vatRate
     * @return array<string, mixed>
     */
    public function calculateSettlementSummary(
        float $laborExclVat,
        float $partsExclVat,
        float $excessDeduction = -4000.00,
        float $vatRate = 0.15
    ): array {
        $subtotal = $laborExclVat + $partsExclVat;
        $vat = round($subtotal * $vatRate, 2);
        $grossTotal = $subtotal + $vat;
        $netSettlement = max(0.0, $grossTotal + $excessDeduction); // Excess is stored as negative number

        return [
            "laborExclVat" => $laborExclVat,
            "partsExclVat" => $partsExclVat,
            "subtotalExclVat" => $subtotal,
            "vatAmount" => $vat,
            "grossTotalInclVat" => $grossTotal,
            "excessDeduction" => $excessDeduction,
            "netInsurerPayable" => $netSettlement
        ];
    }
}
