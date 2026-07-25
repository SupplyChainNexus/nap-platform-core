<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Agents\DocumentIntelligenceAgent;
use NAP\Application\Agents\GovernanceGuardianAgent;
use NAP\Application\Agents\VehicleIntelligenceAgent;
use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Application\Services\AudatexParserService;
use NAP\Infrastructure\EventSourcing\SqlEventStore;
use NAP\Infrastructure\Messaging\InMemoryEventBus;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\NXCaseProjector;
use PHPUnit\Framework\TestCase;
use PDO;

final class AudatexIngestionTest extends TestCase
{
    public function testAudatexClaimIngestionTriggersAgentsAndPersistsStore(): void
    {
        $memoryPdo = new PDO("sqlite::memory:", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $dbAdapter = new DatabaseAdapter($memoryPdo);
        $eventStore = new SqlEventStore($dbAdapter);
        $projector = new NXCaseProjector($dbAdapter);
        $eventBus = new InMemoryEventBus();

        // Subscribe Workforce
        $docAgent = new DocumentIntelligenceAgent();
        $vehicleAgent = new VehicleIntelligenceAgent();
        $govAgent = new GovernanceGuardianAgent();

        $eventBus->subscribe($docAgent);
        $eventBus->subscribe($vehicleAgent);
        $eventBus->subscribe($govAgent);

        $handler = new IngestAudatexClaimHandler(
            new AudatexParserService(),
            $eventStore,
            $eventBus
        );

        $caseId = "NXC-2026-AUDATEX-01";
        $case = $handler->handle($caseId, "RENASA / AUDATEX CLAIM REPORT TEXT STREAM");

        // Assert Aggregate State
        $this->assertEquals("VEHICLE_IDENTIFIED", $case->getStatus());
        $this->assertEquals(3, $case->getVersion());

        // Assert EventStore Streams
        $stream = $eventStore->getStream($caseId);
        $this->assertCount(3, $stream);

        // Assert Agent Workforce Activity
        $this->assertCount(1, $docAgent->getProcessedLogs());
        $this->assertCount(1, $vehicleAgent->getVerifiedVehicles());
        $this->assertCount(3, $govAgent->getAuditTrail());
    }
}
