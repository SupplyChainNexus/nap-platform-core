<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Domain\Model\NXCase;
use NAP\Domain\Events\AuthorizationReceived;
use NAP\Domain\Events\ClaimAssessmentIngested;
use NAP\Domain\Events\VehicleIdentified;
use NAP\Infrastructure\EventSourcing\SqlEventStore;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use PHPUnit\Framework\TestCase;
use PDO;

final class NXCaseTest extends TestCase
{
    private SqlEventStore $eventStore;

    protected function setUp(): void
    {
        $memoryPdo = new PDO("sqlite::memory:", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $dbAdapter = new DatabaseAdapter($memoryPdo);
        $this->eventStore = new SqlEventStore($dbAdapter);
    }

    public function testNXCaseAggregateRecordsAndPersistsLifecycleEvents(): void
    {
        $caseId = "NXC-2026-0001";
        $claimNumber = "SPM 934740 7 26";

        // 1. Open Case from Insurance Authorization
        $case = NXCase::openFromAuthorization($caseId, $claimNumber, [
            "insurer" => "Renasa",
            "insured" => "CA FILTERS (PTY) LTD",
            "assessmentNumber" => "REN03026"
        ]);

        // 2. Ingest Claim Assessment
        $case->ingestClaimAssessment([
            "repairer" => "XLNT PANELBEATERS",
            "totalRepairCostExclVat" => 21100.67,
            "vatAmount" => 3165.10,
            "excessDeduction" => -4000.00
        ]);

        // 3. Identify Vehicle
        $case->identifyVehicle("ADNUSN1D5U0132488", "NISSAN", "NP200/BASE MODEL", 2017);

        // 4. Append Recorded Events to Event Store
        $uncommittedEvents = $case->releaseRecordedEvents();
        $this->assertCount(3, $uncommittedEvents);

        foreach ($uncommittedEvents as $event) {
            $this->eventStore->append($event);
        }

        // 5. Verify Hydrated Event Stream
        $stream = $this->eventStore->getStream($caseId);
        $this->assertCount(3, $stream);

        $this->assertInstanceOf(AuthorizationReceived::class, $stream[0]);
        $this->assertInstanceOf(ClaimAssessmentIngested::class, $stream[1]);
        $this->assertInstanceOf(VehicleIdentified::class, $stream[2]);

        $this->assertEquals("SPM 934740 7 26", $stream[0]->getPayload()["claimNumber"]);
        $this->assertEquals("XLNT PANELBEATERS", $stream[1]->getPayload()["repairer"]);
        $this->assertEquals("ADNUSN1D5U0132488", $stream[2]->getPayload()["vin"]);
    }
}
