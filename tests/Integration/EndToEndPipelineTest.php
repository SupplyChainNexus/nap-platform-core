<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Application\Services\AutomatedPriceBenchmarker;
use NAP\Application\Services\PurchaseOrderOrchestrator;
use NAP\Domain\Events\PriceBenchmarked;
use NAP\Domain\Events\PurchaseOrderIssued;
use NAP\Domain\ValueObjects\SupplierQuote;
use NAP\Infrastructure\Catalogue\InMemoryPartsCatalogue;
use NAP\Infrastructure\EventSourcing\SqlEventStore;
use NAP\Infrastructure\Http\Controllers\CaseQueryController;
use NAP\Infrastructure\Http\Controllers\ClaimIngestController;
use NAP\Infrastructure\Http\Controllers\DashboardController;
use NAP\Infrastructure\Messaging\InMemoryEventBus;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\AnalyticsProjector;
use NAP\Infrastructure\ReadModel\NXCaseProjector;
use PHPUnit\Framework\TestCase;
use PDO;

final class EndToEndPipelineTest extends TestCase
{
    private DatabaseAdapter $db;
    private SqlEventStore $eventStore;
    private InMemoryEventBus $eventBus;
    private NXCaseProjector $caseProjector;
    private AnalyticsProjector $analyticsProjector;

    protected function setUp(): void
    {
        $pdo = new PDO("sqlite::memory:", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $this->db = new DatabaseAdapter($pdo);
        $this->eventStore = new SqlEventStore($this->db);
        $this->eventBus = new InMemoryEventBus();

        $this->caseProjector = new NXCaseProjector($this->db);
        $this->analyticsProjector = new AnalyticsProjector($this->db);
        $this->analyticsProjector->ensureSchemaExists();

        // Initialize read model table
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

    public function testCompleteAudatexToSettlementE2EPipeline(): void
    {
        // 1. Webhook Ingestion
        $ingestHandler = new IngestAudatexClaimHandler(null, $this->eventStore, $this->eventBus);
        $ingestController = new ClaimIngestController($ingestHandler);

        $caseId = "NXC-2026-E2E-999";
        $payload = [
            "caseId" => $caseId,
            "document" => [
                "claimNumber" => "E2E-CLAIM-789",
                "insurer" => "Renasa Insurance Company Ltd",
                "repairer" => "XLNT PANELBEATERS"
            ]
        ];

        $ingestResponse = $ingestController->handleWebhook($payload);
        /** @var array<string, mixed> $ingestData */
        $ingestData = json_decode($ingestResponse, true);
        $this->assertEquals("success", $ingestData["status"]);
        $this->assertEquals(201, $ingestData["code"]);

        // Project initial ingestion events
        $stream = $this->eventStore->getStream($caseId);
        foreach ($stream as $event) {
            $this->caseProjector->project($event);
            $this->analyticsProjector->project($event);
        }

        // 2. Catalogue lookup assertion check
        $catalogue = new InMemoryPartsCatalogue([
            "F2022-1DA0A" => [
                "partNumber" => "F2022-1DA0A",
                "oemPartNumber" => "OEM-NIS-FRONT-BUMPER-2022",
                "description" => "Front Bumper"
            ]
        ]);
        $this->assertInstanceOf(InMemoryPartsCatalogue::class, $catalogue);

        // 3. RFQ Benchmarking & Savings Calculation
        $baselineParts = [
            ["partNumber" => "F2022-1DA0A", "priceExclVat" => 3500.00]
        ];

        $quotes = [
            new SupplierQuote("Supplier Alpha", "F2022-1DA0A", 3500.00, 1, true),
            new SupplierQuote("Supplier Beta", "F2022-1DA0A", 2800.00, 1, true)
        ];

        $benchmarker = new AutomatedPriceBenchmarker();
        $benchmarkResult = $benchmarker->benchmark($baselineParts, $quotes);
        $this->assertEquals(700.00, $benchmarkResult["totalSavingsAmount"]);

        // Record & project PriceBenchmarked event
        $benchmarkedEvent = new PriceBenchmarked($caseId, [
            "savingsAmount" => $benchmarkResult["totalSavingsAmount"],
            "selectedSupplierQuotes" => $benchmarkResult["selectedSupplierQuotes"]
        ]);
        $this->eventStore->append($benchmarkedEvent);
        $this->caseProjector->project($benchmarkedEvent);
        $this->analyticsProjector->project($benchmarkedEvent);

        // 4. Purchase Order Orchestration
        $orchestrator = new PurchaseOrderOrchestrator();
        $pos = $orchestrator->generatePurchaseOrders($caseId, $benchmarkResult["selectedSupplierQuotes"]);
        $this->assertNotEmpty($pos);
        $po = $pos[0];
        $this->assertStringStartsWith("PO-", $po->getPoNumber());

        // Record & project PurchaseOrderIssued event
        $poEvent = new PurchaseOrderIssued($caseId, [
            "poNumber" => $po->getPoNumber(),
            "supplierName" => $po->getSupplierName(),
            "totalExclVat" => $po->getSubtotalExclVat()
        ]);
        $this->eventStore->append($poEvent);
        $this->caseProjector->project($poEvent);
        $this->analyticsProjector->project($poEvent);

        // 5. Query Read Model Endpoint
        $queryController = new CaseQueryController($this->db);
        $caseJsonResponse = $queryController->getCaseDetails($caseId);
        /** @var array<string, mixed> $caseDetails */
        $caseDetails = json_decode($caseJsonResponse, true);
        $this->assertEquals("success", $caseDetails["status"]);
        $this->assertEquals($caseId, $caseDetails["data"]["caseId"]);

        // 6. Query Executive Dashboard Endpoint
        $dashboardController = new DashboardController($this->db);
        $summaryJsonResponse = $dashboardController->getExecutiveSummary();
        /** @var array<string, mixed> $summaryData */
        $summaryData = json_decode($summaryJsonResponse, true);

        $this->assertEquals("success", $summaryData["status"]);
        $this->assertEquals(700.00, $summaryData["data"]["totalSavingsAmount"]);
        $this->assertEquals(1, $summaryData["data"]["benchmarkedQuotesCount"]);
        $this->assertEquals(1, $summaryData["data"]["purchaseOrdersIssuedCount"]);
    }
}