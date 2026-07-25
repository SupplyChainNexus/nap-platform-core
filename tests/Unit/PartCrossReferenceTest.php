<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Services\PartLookupService;
use NAP\Domain\Model\QuoteLineItem;
use NAP\Domain\Model\SupplierQuote;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;
use PHPUnit\Framework\TestCase;

final class PartCrossReferenceTest extends TestCase
{
    public function testPartCrossReferenceIndexingAndLookup(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);

        $repo = new PartCrossReferenceRepository($db);
        $repo->initializeSchema();

        $service = new PartLookupService($repo);

        $item1 = new QuoteLineItem("A2058800100", "GMK-205-FB", "Grandmark", 1200.00, 1800.00);
        $item2 = new QuoteLineItem("A2058800100", "MIT-205-FB", "MIT Auto Parts", 1150.00, 1800.00);

        $quote1 = new SupplierQuote("Q-100", "SUP-001", "CASE-801", [$item1]);
        $quote2 = new SupplierQuote("Q-101", "SUP-002", "CASE-802", [$item2]);

        $service->indexQuote($quote1);
        $service->indexQuote($quote2);

        $alternatives = $service->getAlternativeParts("A2058800100");

        $this->assertCount(2, $alternatives);
        $this->assertEquals("MIT-205-FB", $alternatives[0]["supplierPartNumber"]);
        $this->assertEquals("MIT Auto Parts", $alternatives[0]["brandName"]);
        $this->assertEquals(1150.00, $alternatives[0]["lastQuotedPrice"]);
    }
}