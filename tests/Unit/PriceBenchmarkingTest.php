<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Services\AutomatedPriceBenchmarker;
use NAP\Domain\Events\PriceBenchmarked;
use NAP\Domain\ValueObjects\SupplierQuote;
use PHPUnit\Framework\TestCase;

final class PriceBenchmarkingTest extends TestCase
{
    public function testPriceBenchmarkerCalculatesSavingsAndSelectsBestSupplier(): void
    {
        $benchmarker = new AutomatedPriceBenchmarker();

        $baselineParts = [
            [
                "partNumber" => "F2022-1DA0A",
                "description" => "FRONT BUMPER COVER",
                "priceExclVat" => 4250.00
            ],
            [
                "partNumber" => "26010-1DA0A",
                "description" => "HEADLAMP ASSY RH",
                "priceExclVat" => 3800.00
            ]
        ];

        $quotes = [
            new SupplierQuote("Grandmark", "F2022-1DA0A", 3600.00, 1, true),
            new SupplierQuote("Midas", "F2022-1DA0A", 3900.00, 2, true),
            new SupplierQuote("Goldwagen", "26010-1DA0A", 3100.00, 1, true),
        ];

        $result = $benchmarker->benchmark($baselineParts, $quotes);

        $this->assertEquals(8050.00, $result["baselinePartsTotalExclVat"]);
        $this->assertEquals(6700.00, $result["benchmarkedPartsTotalExclVat"]);
        $this->assertEquals(1350.00, $result["totalSavingsAmount"]);
        $this->assertEquals(16.77, $result["savingsPercentage"]);
        $this->assertCount(2, $result["selectedSupplierQuotes"]);
    }

    public function testPriceBenchmarkedEventInstantiation(): void
    {
        $event = new PriceBenchmarked("NXC-2026-TEST", [
            "totalSavingsAmount" => 1350.00,
            "savingsPercentage" => 16.77
        ]);

        $this->assertEquals("NXC-2026-TEST", $event->getAggregateId());
        $this->assertEquals("PriceBenchmarked", $event->getEventName());
        $this->assertEquals(1350.00, $event->getPayload()["totalSavingsAmount"]);
    }
}
