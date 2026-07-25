<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Domain\Model\NXCase;
use NAP\Infrastructure\EventSourcing\EventReplayEngine;
use NAP\Infrastructure\EventSourcing\SqlEventStore;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\NXCaseProjector;
use PHPUnit\Framework\TestCase;
use PDO;

final class EventReplayTest extends TestCase
{
    private SqlEventStore $eventStore;
    private NXCaseProjector $projector;
    private EventReplayEngine $replayEngine;

    protected function setUp(): void
    {
        $memoryPdo = new PDO("sqlite::memory:", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $dbAdapter = new DatabaseAdapter($memoryPdo);
        $this->eventStore = new SqlEventStore($dbAdapter);
        $this->projector = new NXCaseProjector($dbAdapter);
        $this->replayEngine = new EventReplayEngine($this->eventStore);
        $this->replayEngine->addProjection($this->projector);
    }

    public function testEventReplayEngineRebuildsReadModelFromEventStore(): void
    {
        $caseId = "NXC-2026-7700";

        // 1. Create case and record events
        $case = NXCase::openFromAuthorization($caseId, "SPM 934740 7 26", [
            "insurer" => "Renasa",
            "insured" => "CA FILTERS (PTY) LTD"
        ]);
        $case->ingestClaimAssessment([
            "repairer" => "XLNT PANELBEATERS",
            "totalRepairCostExclVat" => 21100.67
        ]);
        $case->identifyVehicle("ADNUSN1D5U0132488", "NISSAN", "NP200", 2017);

        // 2. Append events to Event Store
        foreach ($case->releaseRecordedEvents() as $event) {
            $this->eventStore->append($event);
        }

        // 3. Replay Stream to build Projections
        $replayedCount = $this->replayEngine->rebuildAllProjections($caseId);
        $this->assertEquals(3, $replayedCount);

        // 4. Assert Read Model state
        $readModel = $this->projector->getReadModel($caseId);
        $this->assertNotNull($readModel);
        $this->assertEquals("NXC-2026-7700", $readModel["case_id"]);
        $this->assertEquals("SPM 934740 7 26", $readModel["claim_number"]);
        $this->assertEquals("Renasa", $readModel["insurer"]);
        $this->assertEquals("XLNT PANELBEATERS", $readModel["repairer"]);
        $this->assertEquals("ADNUSN1D5U0132488", $readModel["vin"]);
        $this->assertEquals("VEHICLE_IDENTIFIED", $readModel["status"]);
        $this->assertEquals(21100.67, $readModel["total_repair_cost"]);
    }
}
