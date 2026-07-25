<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Agents\DocumentIntelligenceAgent;
use NAP\Application\Agents\GovernanceGuardianAgent;
use NAP\Application\Agents\VehicleIntelligenceAgent;
use NAP\Domain\Model\NXCase;
use NAP\Infrastructure\Messaging\InMemoryEventBus;
use PHPUnit\Framework\TestCase;

final class EventBusTest extends TestCase
{
    public function testEventBusDispatchesEventsToSubscribedAgents(): void
    {
        $eventBus = new InMemoryEventBus();

        $docAgent = new DocumentIntelligenceAgent();
        $vehicleAgent = new VehicleIntelligenceAgent();
        $govAgent = new GovernanceGuardianAgent();

        $eventBus->subscribe($docAgent);
        $eventBus->subscribe($vehicleAgent);
        $eventBus->subscribe($govAgent);

        $this->assertEquals(3, $eventBus->getListenerCount());

        // Open NXCase from Insurance Authorization
        $case = NXCase::openFromAuthorization("NXC-2026-8800", "SPM 934740 7 26", [
            "insurer" => "Renasa",
            "insured" => "CA FILTERS (PTY) LTD",
            "assessmentNumber" => "REN03026"
        ]);

        $case->identifyVehicle("ADNUSN1D5U0132488", "NISSAN", "NP200/BASE MODEL", 2017);

        // Dispatch all recorded aggregate events
        $events = $case->releaseRecordedEvents();
        $eventBus->dispatchAll($events);

        // Assert Agents Processed Events Correctly
        $this->assertCount(1, $docAgent->getProcessedLogs());
        $this->assertEquals("SPM 934740 7 26", $docAgent->getProcessedLogs()[0]["claimNumber"]);

        $this->assertCount(1, $vehicleAgent->getVerifiedVehicles());
        $this->assertEquals("ADNUSN1D5U0132488", $vehicleAgent->getVerifiedVehicles()[0]["vin"]);

        // Governance Guardian listened to BOTH events
        $this->assertCount(2, $govAgent->getAuditTrail());
        $this->assertEquals("AuthorizationReceived", $govAgent->getAuditTrail()[0]["eventName"]);
        $this->assertEquals("VehicleIdentified", $govAgent->getAuditTrail()[1]["eventName"]);
    }
}
