<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Domain\ValueObjects\PurchaseOrder;
use NAP\Infrastructure\Connectors\HttpSupplierConnector;
use NAP\Infrastructure\Connectors\SupplierConnectorRegistry;
use PHPUnit\Framework\TestCase;

final class SupplierConnectorTest extends TestCase
{
    public function testHttpSupplierConnectorRfqAndPoDispatch(): void
    {
        $connector = new HttpSupplierConnector("Supplier Alpha", "https://api.supplieralpha.co.za/v1", "key-123");

        $caseId = "NXC-2026-TEST";
        $parts = [
            ["partNumber" => "F2022-1DA0A", "description" => "Front Bumper", "quantity" => 1]
        ];

        $rfqResult = $connector->requestQuotes($caseId, $parts);
        $this->assertEquals("SUCCESS", $rfqResult["status"]);
        $this->assertEquals("Supplier Alpha", $rfqResult["supplier"]);
        $this->assertEquals(1, $rfqResult["quotesCount"]);

        $po = new PurchaseOrder("PO-001", $caseId, "Supplier Alpha", $parts, 2800.00);
        $dispatched = $connector->dispatchPurchaseOrder($po);
        $this->assertTrue($dispatched);
    }

    public function testSupplierConnectorRegistryBroadcastAndDispatch(): void
    {
        $alpha = new HttpSupplierConnector("Supplier Alpha", "https://api.alpha.co.za");
        $beta = new HttpSupplierConnector("Supplier Beta", "https://api.beta.co.za");

        $registry = new SupplierConnectorRegistry([$alpha, $beta]);
        $this->assertEquals(2, $registry->getConnectorCount());

        $parts = [
            ["partNumber" => "F2022-1DA0A", "description" => "Front Bumper"]
        ];

        $broadcastResults = $registry->broadcastRfq("NXC-2026-TEST", $parts);
        $this->assertArrayHasKey("Supplier Alpha", $broadcastResults);
        $this->assertArrayHasKey("Supplier Beta", $broadcastResults);

        $po = new PurchaseOrder("PO-002", "NXC-2026-TEST", "Supplier Beta", $parts, 3100.00);
        $dispatched = $registry->dispatchPo($po);
        $this->assertTrue($dispatched);
    }
}