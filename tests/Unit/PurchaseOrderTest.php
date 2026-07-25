<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Services\PurchaseOrderOrchestrator;
use NAP\Domain\Events\PurchaseOrderIssued;
use NAP\Domain\ValueObjects\PurchaseOrder;
use PHPUnit\Framework\TestCase;

final class PurchaseOrderTest extends TestCase
{
    public function testOrchestratorGroupsQuotesAndGeneratesPurchaseOrders(): void
    {
        $orchestrator = new PurchaseOrderOrchestrator();
        $caseId = "NXC-2026-SETTLE-01";

        $selectedQuotes = [
            [
                "supplierName" => "Grandmark",
                "partNumber" => "F2022-1DA0A",
                "unitPriceExclVat" => 3600.00
            ],
            [
                "supplierName" => "Goldwagen",
                "partNumber" => "26010-1DA0A",
                "unitPriceExclVat" => 3100.00
            ]
        ];

        $pos = $orchestrator->generatePurchaseOrders($caseId, $selectedQuotes);

        $this->assertCount(2, $pos);
        $this->assertInstanceOf(PurchaseOrder::class, $pos[0]);
        $this->assertEquals("Grandmark", $pos[0]->getSupplierName());
        $this->assertEquals(3600.00, $pos[0]->getSubtotalExclVat());
        $this->assertEquals(540.00, $pos[0]->getVatAmount());
        $this->assertEquals(4140.00, $pos[0]->getTotalAmountInclVat());
    }

    public function testCalculateSettlementSummary(): void
    {
        $orchestrator = new PurchaseOrderOrchestrator();

        $summary = $orchestrator->calculateSettlementSummary(
            laborExclVat: 2600.67,
            partsExclVat: 6700.00,
            excessDeduction: -4000.00
        );

        $this->assertEquals(9300.67, $summary["subtotalExclVat"]);
        $this->assertEquals(1395.10, $summary["vatAmount"]);
        $this->assertEquals(10695.77, $summary["grossTotalInclVat"]);
        $this->assertEquals(-4000.00, $summary["excessDeduction"]);
        $this->assertEquals(6695.77, $summary["netInsurerPayable"]);
    }

    public function testPurchaseOrderIssuedEvent(): void
    {
        $event = new PurchaseOrderIssued("NXC-2026-SETTLE-01", [
            "poNumber" => "PO-ABC123-001",
            "supplier" => "Grandmark"
        ]);

        $this->assertEquals("NXC-2026-SETTLE-01", $event->getAggregateId());
        $this->assertEquals("PurchaseOrderIssued", $event->getEventName());
        $this->assertEquals("PO-ABC123-001", $event->getPayload()["poNumber"]);
    }
}
