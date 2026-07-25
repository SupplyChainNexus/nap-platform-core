<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\IssuePurchaseOrderHandler;
use NAP\Domain\Model\QuoteLineItem;
use NAP\Domain\Model\SupplierQuote;
use PHPUnit\Framework\TestCase;

final class SupplierQuoteAndPoTest extends TestCase
{
    public function testQuoteLineItemRequiresSupplierPartNumberAndBrand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Supplier part number is strictly required for transparency.");

        new QuoteLineItem("A2058800100", "", "Grandmark", 1200.00, 1800.00);
    }

    public function testQuoteLineItemRequiresBrandName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Brand name is strictly required.");

        new QuoteLineItem("A2058800100", "GMK-205-FB", "   ", 1200.00, 1800.00);
    }

    public function testSupplierQuoteCalculatesAggregateSavings(): void
    {
        $item1 = new QuoteLineItem("A2058800100", "GMK-205-FB", "Grandmark", 1200.00, 1500.00);
        $item2 = new QuoteLineItem("A2058800200", "MIT-205-HL", "MIT Auto Parts", 800.00, 1100.00);

        $quote = new SupplierQuote("Q-100", "SUP-001", "CASE-801", [$item1, $item2]);

        $this->assertEquals(2000.00, $quote->getQuotedAmount());
        $this->assertEquals(2600.00, $quote->getBenchmarkPrice());
        $this->assertEquals(600.00, $quote->calculateSavings());
        $this->assertTrue($quote->isEligibleForAutoPo(50.0));
    }

    public function testAutoPoHandlerIssuesOrderWithLineItemMetadata(): void
    {
        $item = new QuoteLineItem("A2058800100", "GMK-205-FB", "Grandmark", 800.00, 1000.00);
        $quote = new SupplierQuote("Q-101", "SUP-002", "CASE-802", [$item]);

        $handler = new IssuePurchaseOrderHandler();
        $event = $handler->handle($quote, 100.00);

        $this->assertNotNull($event);
        $this->assertEquals("PurchaseOrderIssued", $event->getEventName());

        /** @var array{quoteId: string, savingsAmount: float, lineItems: array<int, array{supplierPartNumber: string, brandName: string}>} $payload */
        $payload = $event->getPayload();

        $this->assertEquals("Q-101", $payload["quoteId"]);
        $this->assertEquals(200.00, $payload["savingsAmount"]);
        $this->assertEquals("GMK-205-FB", $payload["lineItems"][0]["supplierPartNumber"]);
        $this->assertEquals("Grandmark", $payload["lineItems"][0]["brandName"]);
    }
}