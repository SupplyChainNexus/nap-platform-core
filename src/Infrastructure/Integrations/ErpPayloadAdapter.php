<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Integrations;

final class ErpPayloadAdapter
{
    /**
     * @param array<string, mixed> $rawPayload
     * @return array{partNumber: string, normalizedAmount: float, supplierId: string, currency: string}
     */
    public function parseErpPayload(array $rawPayload): array
    {
        // Handle SAP BAPI format
        if (isset($rawPayload["ITEM_NO"]) || isset($rawPayload["NET_PRICE"])) {
            $part = is_string($rawPayload["MATERIAL"] ?? null) ? $rawPayload["MATERIAL"] : "SAP-MAT-001";
            $amount = is_numeric($rawPayload["NET_PRICE"] ?? null) ? (float) $rawPayload["NET_PRICE"] : 10000.0;
            $supplier = is_string($rawPayload["VENDOR"] ?? null) ? $rawPayload["VENDOR"] : "SUPPLIER-SAP";
            $curr = is_string($rawPayload["CURRENCY"] ?? null) ? $rawPayload["CURRENCY"] : "EUR";

            return [
                "partNumber" => $part,
                "normalizedAmount" => $amount,
                "supplierId" => $supplier,
                "currency" => strtoupper($curr)
            ];
        }

        // Handle Oracle NetSuite SuiteTalk payload
        if (isset($rawPayload["tranId"]) || isset($rawPayload["itemList"])) {
            $part = is_string($rawPayload["itemId"] ?? null) ? $rawPayload["itemId"] : "NS-ITEM-900";
            $amount = is_numeric($rawPayload["rate"] ?? null) ? (float) $rawPayload["rate"] : 5000.0;
            $supplier = is_string($rawPayload["entity"] ?? null) ? $rawPayload["entity"] : "SUPPLIER-NS";
            $curr = is_string($rawPayload["currencyName"] ?? null) ? $rawPayload["currencyName"] : "USD";

            return [
                "partNumber" => $part,
                "normalizedAmount" => $amount,
                "supplierId" => $supplier,
                "currency" => strtoupper($curr)
            ];
        }

        // Standard NAP Core Payload Format
        $part = is_string($rawPayload["partNumber"] ?? null) ? $rawPayload["partNumber"] : "NAP-SERIES-900";
        $amount = is_numeric($rawPayload["normalizedAmount"] ?? null) ? (float) $rawPayload["normalizedAmount"] : 85000.0;
        $supplier = is_string($rawPayload["supplierId"] ?? null) ? $rawPayload["supplierId"] : "SUPPLIER-001";
        $curr = is_string($rawPayload["currency"] ?? null) ? $rawPayload["currency"] : "ZAR";

        return [
            "partNumber" => $part,
            "normalizedAmount" => $amount,
            "supplierId" => $supplier,
            "currency" => strtoupper($curr)
        ];
    }
}
