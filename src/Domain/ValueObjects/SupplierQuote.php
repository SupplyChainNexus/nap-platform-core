<?php

declare(strict_types=1);

namespace NAP\Domain\ValueObjects;

final class SupplierQuote
{
    private string $supplierName;
    private string $partNumber;
    private float $unitPriceExclVat;
    private int $leadTimeDays;
    private bool $isOemEquivalent;

    public function __construct(
        string $supplierName,
        string $partNumber,
        float $unitPriceExclVat,
        int $leadTimeDays = 1,
        bool $isOemEquivalent = true
    ) {
        $this->supplierName = trim($supplierName);
        $this->partNumber = trim($partNumber);
        $this->unitPriceExclVat = max(0.0, $unitPriceExclVat);
        $this->leadTimeDays = max(0, $leadTimeDays);
        $this->isOemEquivalent = $isOemEquivalent;
    }

    public function getSupplierName(): string
    {
        return $this->supplierName;
    }

    public function getPartNumber(): string
    {
        return $this->partNumber;
    }

    public function getUnitPriceExclVat(): float
    {
        return $this->unitPriceExclVat;
    }

    public function getLeadTimeDays(): int
    {
        return $this->leadTimeDays;
    }

    public function isOemEquivalent(): bool
    {
        return $this->isOemEquivalent;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            "supplierName" => $this->supplierName,
            "partNumber" => $this->partNumber,
            "unitPriceExclVat" => $this->unitPriceExclVat,
            "leadTimeDays" => $this->leadTimeDays,
            "isOemEquivalent" => $this->isOemEquivalent
        ];
    }
}
