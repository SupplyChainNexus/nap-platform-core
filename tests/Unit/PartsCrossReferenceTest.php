<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Services\PartsCrossReferenceService;
use NAP\Domain\Events\PartNormalized;
use NAP\Infrastructure\Catalogue\InMemoryPartsCatalogue;
use PHPUnit\Framework\TestCase;

final class PartsCrossReferenceTest extends TestCase
{
    public function testCrossReferenceServiceMatchesOemCatalogue(): void
    {
        $catalogue = new InMemoryPartsCatalogue();
        $service = new PartsCrossReferenceService($catalogue);

        $rawParts = [
            [
                "guideNumber" => "2311",
                "partNumber" => "UNKNOWN",
                "description" => "FRONT BUMPER COVER",
                "priceExclVat" => 4250.00
            ],
            [
                "guideNumber" => "9999",
                "partNumber" => "CUSTOM-PART-01",
                "description" => "CUSTOM UNKNOWN BRACKET",
                "priceExclVat" => 150.00
            ]
        ];

        $results = $service->normalizePartsList("NISSAN", "NP200/BASE MODEL", $rawParts);

        $this->assertCount(2, $results);

        // First item matched OEM catalogue
        $this->assertEquals("F2022-1DA0A", $results[0]["partNumber"]);
        $this->assertTrue($results[0]["matched"]);
        $this->assertEquals(1.0, $results[0]["confidence"]);

        // Second item unmatched fallback
        $this->assertEquals("CUSTOM-PART-01", $results[1]["partNumber"]);
        $this->assertFalse($results[1]["matched"]);
        $this->assertEquals(0.80, $results[1]["confidence"]);
    }

    public function testPartNormalizedEventInstantiation(): void
    {
        $event = new PartNormalized("NXC-2026-TEST", [
            "partNumber" => "F2022-1DA0A",
            "matched" => true
        ], 1);

        $this->assertEquals("NXC-2026-TEST", $event->getAggregateId());
        $this->assertEquals("PartNormalized", $event->getEventName());
        $this->assertTrue($event->getPayload()["matched"]);
    }
}
