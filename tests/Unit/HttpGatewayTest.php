<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Infrastructure\EventSourcing\SqlEventStore;
use NAP\Infrastructure\Http\Controllers\CaseQueryController;
use NAP\Infrastructure\Http\Controllers\ClaimIngestController;
use NAP\Infrastructure\Messaging\InMemoryEventBus;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\NXCaseProjector;
use PHPUnit\Framework\TestCase;
use PDO;

final class HttpGatewayTest extends TestCase
{
    private DatabaseAdapter $db;
    private SqlEventStore $eventStore;

    protected function setUp(): void
    {
        $pdo = new PDO("sqlite::memory:", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $this->db = new DatabaseAdapter($pdo);
        $this->eventStore = new SqlEventStore($this->db);

        // Create read model schema matching all NXCaseProjector query/upsert columns
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nx_case_read_model (
                case_id VARCHAR(64) PRIMARY KEY,
                claim_number VARCHAR(64),
                insurer VARCHAR(128),
                insured VARCHAR(128),
                repairer VARCHAR(128),
                vin VARCHAR(64),
                make VARCHAR(64),
                model VARCHAR(64),
                vehicle_make VARCHAR(64),
                vehicle_model VARCHAR(64),
                vehicle_year INT,
                status VARCHAR(64),
                total_repair_cost DECIMAL(12,2),
                total_repair_cost_excl_vat DECIMAL(12,2),
                version INT,
                updated_at VARCHAR(32),
                last_updated VARCHAR(32)
            );
        ");
    }

    public function testClaimIngestControllerHandlesWebhookAndReturns201(): void
    {
        $eventBus = new InMemoryEventBus();
        $handler = new IngestAudatexClaimHandler(null, $this->eventStore, $eventBus);
        $controller = new ClaimIngestController($handler);

        $payload = [
            "caseId" => "NXC-2026-HTTP-01",
            "document" => [
                "claimNumber" => "SPM 934740 7 26",
                "insurer" => "Renasa Insurance Company Ltd",
                "repairer" => "XLNT PANELBEATERS"
            ]
        ];

        $jsonOutput = $controller->handleWebhook($payload);
        
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($jsonOutput, true);

        $this->assertEquals("success", $decoded["status"]);
        $this->assertEquals(201, $decoded["code"]);
        $this->assertEquals("NXC-2026-HTTP-01", $decoded["data"]["caseId"]);
        $this->assertEquals("VEHICLE_IDENTIFIED", $decoded["data"]["status"]);
    }

    public function testCaseQueryControllerReturnsReadModelData(): void
    {
        $eventBus = new InMemoryEventBus();
        $handler = new IngestAudatexClaimHandler(null, $this->eventStore, $eventBus);
        $caseId = "NXC-2026-QUERY-01";
        
        $case = $handler->handle($caseId, []);

        // Project events to read model DB
        $projector = new NXCaseProjector($this->db);
        $stream = $this->eventStore->getStream($caseId);
        foreach ($stream as $event) {
            $projector->project($event);
        }

        $queryController = new CaseQueryController($this->db);
        $jsonOutput = $queryController->getCaseDetails($caseId);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($jsonOutput, true);

        $this->assertEquals("success", $decoded["status"]);
        $this->assertEquals(200, $decoded["code"]);
        $this->assertEquals($caseId, $decoded["data"]["caseId"]);
        $this->assertEquals("NISSAN", $decoded["data"]["vehicleMake"]);
    }

    public function testCaseQueryControllerReturns404WhenNotFound(): void
    {
        $queryController = new CaseQueryController($this->db);
        $jsonOutput = $queryController->getCaseDetails("NON_EXISTENT_CASE");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($jsonOutput, true);

        $this->assertEquals("error", $decoded["status"]);
        $this->assertEquals(404, $decoded["code"]);
    }
}