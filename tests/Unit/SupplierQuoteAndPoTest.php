<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\IssuePurchaseOrderHandler;
use NAP\Domain\Model\SupplierQuote;
use PHPUnit\Framework\TestCase;

final class SupplierQuoteAndPoTest extends TestCase
{
    public function testSupplierQuoteCalculatesSavings(): void
    {
        $quote = new SupplierQuote("Q-100", "SUP-001", "CASE-801", 1200.00, 1500.00);

        $this->assertEquals(300.00, $quote->calculateSavings());
        $this->assertTrue($quote->isEligibleForAutoPo(50.0));
    }

    public function testAutoPoHandlerIssuesOrderWhenEligible(): void
    {
        $quote = new SupplierQuote("Q-101", "SUP-002", "CASE-802", 800.00, 1000.00);
        $handler = new IssuePurchaseOrderHandler();

        $event = $handler->handle($quote, 100.00);

        $this->assertNotNull($event);
        $this->assertEquals("PurchaseOrderIssued", $event->getEventName());
        
        $payload = $event->getPayload();
        $this->assertEquals("Q-101", $payload["quoteId"]);
        $this->assertEquals(200.00, $payload["savingsAmount"]);
    }

    public function testAutoPoHandlerRejectsOrderBelowThreshold(): void
    {
        $quote = new SupplierQuote("Q-102", "SUP-003", "CASE-803", 980.00, 1000.00);
        $handler = new IssuePurchaseOrderHandler();

        $event = $handler->handle($quote, 50.00);

        $this->assertNull($event);
    }
}
