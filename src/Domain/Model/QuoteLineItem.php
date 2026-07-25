<?php

declare(strict_types=1);

namespace NAP\Domain\Model;

final class QuoteLineItem
{
    private string $oemPartNumber;
    private string $supplierPartNumber;
    private string $brandName;
    private float $quotedPrice;
    private float $benchmarkOemPrice;

    public function __construct(
        string $oemPartNumber,
        string $supplierPartNumber,
        string $brandName,
        float $quotedPrice,
        float $benchmarkOemPrice
    ) {
        $oem = trim($oemPartNumber);
        $supplierPart = trim($supplierPartNumber);
        $brand = trim($brandName);

        if ($oem === '') {
            throw new \InvalidArgumentException("Target OEM part number cannot be empty.");
        }

        if ($supplierPart === '') {
            throw new \InvalidArgumentException("Supplier part number is strictly required for transparency.");
        }

        if ($brand === '') {
            throw new \InvalidArgumentException("Brand name is strictly required.");
        }

        if ($quotedPrice <= 0) {
            throw new \InvalidArgumentException("Quoted price must be greater than zero.");
        }

        if ($benchmarkOemPrice <= 0) {
            throw new \InvalidArgumentException("Benchmark OEM price must be greater than zero.");
        }

        $this->oemPartNumber = strtoupper($oem);
        $this->supplierPartNumber = strtoupper($supplierPart);
        $this->brandName = $brand;
        $this->quotedPrice = $quotedPrice;
        $this->benchmarkOemPrice = $benchmarkOemPrice;
    }

    public function getOemPartNumber(): string
    {
        return $this->oemPartNumber;
    }

    public function getSupplierPartNumber(): string
    {
        return $this->supplierPartNumber;
    }

    public function getBrandName(): string
    {
        return $this->brandName;
    }

    public function getQuotedPrice(): float
    {
        return $this->quotedPrice;
    }

    public function getBenchmarkOemPrice(): float
    {
        return $this->benchmarkOemPrice;
    }

    public function calculateSavings(): float
    {
        return max(0.0, $this->benchmarkOemPrice - $this->quotedPrice);
    }

    /**
     * @return array{oemPartNumber: string, supplierPartNumber: string, brandName: string, quotedPrice: float, benchmarkOemPrice: float, savings: float}
     */
    public function toArray(): array
    {
        return [
            "oemPartNumber" => $this->oemPartNumber,
            "supplierPartNumber" => $this->supplierPartNumber,
            "brandName" => $this->brandName,
            "quotedPrice" => $this->quotedPrice,
            "benchmarkOemPrice" => $this->benchmarkOemPrice,
            "savings" => $this->calculateSavings(),
        ];
    }
}