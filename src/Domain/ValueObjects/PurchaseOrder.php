<?php

declare(strict_types=1);

namespace NAP\Domain\ValueObjects;

final class PurchaseOrder
{
    private string $poNumber;
    private string $caseId;
    private string $supplierName;
    /** @var array<int, array<string, mixed>> */
    private array $lineItems;
    private float $subtotalExclVat;
    private float $vatAmount;
    private float $totalAmountInclVat;
    private string $status;

    /**
     * @param string $poNumber
     * @param string $caseId
     * @param string $supplierName
     * @param array<int, array<string, mixed>> $lineItems
     * @param float $subtotalExclVat
     * @param float $vatRate
     * @param string $status
     */
    public function __construct(
        string $poNumber,
        string $caseId,
        string $supplierName,
        array $lineItems,
        float $subtotalExclVat,
        float $vatRate = 0.15,
        string $status = "ISSUED"
    ) {
        $this->poNumber = trim($poNumber);
        $this->caseId = trim($caseId);
        $this->supplierName = trim($supplierName);
        $this->lineItems = $lineItems;
        $this->subtotalExclVat = max(0.0, $subtotalExclVat);
        $this->vatAmount = round($this->subtotalExclVat * $vatRate, 2);
        $this->totalAmountInclVat = round($this->subtotalExclVat + $this->vatAmount, 2);
        $this->status = trim($status);
    }

    public function getPoNumber(): string
    {
        return $this->poNumber;
    }

    public function getCaseId(): string
    {
        return $this->caseId;
    }

    public function getSupplierName(): string
    {
        return $this->supplierName;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }

    public function getSubtotalExclVat(): float
    {
        return $this->subtotalExclVat;
    }

    public function getVatAmount(): float
    {
        return $this->vatAmount;
    }

    public function getTotalAmountInclVat(): float
    {
        return $this->totalAmountInclVat;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            "poNumber" => $this->poNumber,
            "caseId" => $this->caseId,
            "supplierName" => $this->supplierName,
            "lineItems" => $this->lineItems,
            "subtotalExclVat" => $this->subtotalExclVat,
            "vatAmount" => $this->vatAmount,
            "totalAmountInclVat" => $this->totalAmountInclVat,
            "status" => $this->status
        ];
    }
}
