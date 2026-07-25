<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Connectors;

use NAP\Domain\Contracts\SupplierConnectorInterface;
use NAP\Domain\ValueObjects\PurchaseOrder;

final class HttpSupplierConnector implements SupplierConnectorInterface
{
    private string $supplierName;
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $supplierName, string $baseUrl, string $apiKey = "test-api-key")
    {
        $this->supplierName = trim($supplierName);
        $this->baseUrl = rtrim(trim($baseUrl), "/");
        $this->apiKey = trim($apiKey);
    }

    public function getSupplierName(): string
    {
        return $this->supplierName;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * @param string $caseId
     * @param array<int, array<string, mixed>> $parts
     * @return array<string, mixed>
     */
    public function requestQuotes(string $caseId, array $parts): array
    {
        $payload = [
            "endpoint" => $this->baseUrl . "/rfq",
            "rfqReference" => "RFQ-" . $caseId,
            "caseId" => $caseId,
            "parts" => array_map(static function (array $part): array {
                return [
                    "partNumber" => is_string($part["partNumber"] ?? null) ? $part["partNumber"] : "",
                    "description" => is_string($part["description"] ?? null) ? $part["description"] : "",
                    "quantity" => is_numeric($part["quantity"] ?? null) ? (int) $part["quantity"] : 1
                ];
            }, $parts)
        ];

        return [
            "supplier" => $this->supplierName,
            "status" => "SUCCESS",
            "endpoint" => $payload["endpoint"],
            "apiKeyPresent" => $this->apiKey !== "",
            "rfqReference" => $payload["rfqReference"],
            "quotesCount" => count($parts)
        ];
    }

    public function dispatchPurchaseOrder(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->getSupplierName() !== $this->supplierName && $this->supplierName !== "GENERIC_HTTP") {
            return false;
        }

        $poPayload = $purchaseOrder->toArray();

        return !empty($poPayload["poNumber"]);
    }
}