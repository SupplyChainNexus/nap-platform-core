<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Agents\PricingIntelligenceAgent;
use NAP\Domain\Model\NXCase;
use NAP\Infrastructure\Integrations\LLM\MockLlmProvider;
use NAP\Infrastructure\Messaging\InMemoryEventBus;
use PHPUnit\Framework\TestCase;

final class LlmAbstractionTest extends TestCase
{
    public function testPricingAgentUsesLlmAbstractionSeamlessly(): void
    {
        $mockLlm = new MockLlmProvider('{"decision": "OPTIMIZED", "confidence": 0.98}');
        $pricingAgent = new PricingIntelligenceAgent($mockLlm);

        $eventBus = new InMemoryEventBus();
        $eventBus->subscribe($pricingAgent);

        // Open Case and Ingest Claim
        $case = NXCase::openFromAuthorization("NXC-2026-9900", "SPM 934740 7 26", [
            "insurer" => "Renasa"
        ]);
        $case->ingestClaimAssessment([
            "repairer" => "XLNT PANELBEATERS",
            "totalRepairCostExclVat" => 21100.67
        ]);

        // Dispatch events
        $events = $case->releaseRecordedEvents();
        $eventBus->dispatchAll($events);

        // Assert agent processed event via LLM provider abstraction
        $evaluations = $pricingAgent->getEvaluations();
        $this->assertCount(1, $evaluations);
        $this->assertEquals("PricingIntelligenceAgent", $evaluations[0]["agent"]);
        $this->assertEquals("Mock LLM Provider", $evaluations[0]["provider"]);
        $this->assertEquals("OPTIMIZED", $evaluations[0]["decision"]);
        $this->assertEquals(0.98, $evaluations[0]["confidence"]);
    }
}
