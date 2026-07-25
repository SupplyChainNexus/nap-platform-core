<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Domain\Events\PartNormalized;
use NAP\Domain\Events\PriceBenchmarked;
use NAP\Domain\Events\PurchaseOrderIssued;
use NAP\Infrastructure\Http\Controllers\DashboardController;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\AnalyticsProjector;
use PHPUnit\Framework\TestCase;
use PDO;

final class AnalyticsDashboardTest extends TestCase
{
    private DatabaseAdapter $db;
    private AnalyticsProjector $projector;

    protected function setUp(): void
    {
        $pdo = new PDO("sqlite::memory:", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $this->db = new DatabaseAdapter($pdo);
        $this->projector = new AnalyticsProjector($this->db);
        $this->projector->ensureSchemaExists();
    }

    public function testAnalyticsProjectorIncrementsMetrics(): void
    {
        $event1 = new PriceBenchmarked("NXC-2026-ANA-01", [
            "savingsAmount" => 1250.50
        ]);
        $event2 = new PriceBenchmarked("NXC-2026-ANA-02", [
            "savingsAmount" => 750.25
        ]);
        $event3 = new PurchaseOrderIssued("NXC-2026-ANA-01", [
            "poNumber" => "PO-1001"
        ]);
        $event4 = new PartNormalized("NXC-2026-ANA-01", [
            "partNumber" => "F2022-1DA0A"
        ]);

        $this->projector->project($event1);
        $this->projector->project($event2);
        $this->projector->project($event3);
        $this->projector->project($event4);

        $controller = new DashboardController($this->db);
        $jsonOutput = $controller->getExecutiveSummary();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($jsonOutput, true);

        $this->assertEquals("success", $decoded["status"]);
        $this->assertEquals(200, $decoded["code"]);
        $this->assertEquals(2000.75, $decoded["data"]["totalSavingsAmount"]);
        $this->assertEquals(2, $decoded["data"]["benchmarkedQuotesCount"]);
        $this->assertEquals(1, $decoded["data"]["purchaseOrdersIssuedCount"]);
        $this->assertEquals(1, $decoded["data"]["partsNormalizedCount"]);
    }
}