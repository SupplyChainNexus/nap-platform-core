<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Infrastructure\Http\Controllers\ClaimIngestController;
use NAP\Infrastructure\Logging\JsonLogger;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Security\IdempotencyGuard;

use PHPUnit\Framework\TestCase;

final class SystemHardeningTest extends TestCase
{
    public function testIdempotencyGuardDeduplicatesReplays(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);
        $guard = new IdempotencyGuard($db);

        $handler = new IngestAudatexClaimHandler();
        $controller = new ClaimIngestController($handler, null, $guard);

        $payload = ["caseId" => "NXC-900", "eventId" => "evt-abc-123", "document" => []];
        $rawBody = (string) json_encode($payload);

        // First dispatch -> Processed (201)
        $res1 = $controller->handleWebhook($payload, $rawBody, null, "evt-abc-123");
        /** @var array<string, mixed> $data1 */
        $data1 = json_decode($res1, true);
        $this->assertEquals(201, $data1["code"]);

        // Replay dispatch -> Deduplicated (200)
        $res2 = $controller->handleWebhook($payload, $rawBody, null, "evt-abc-123");
        /** @var array<string, mixed> $data2 */
        $data2 = json_decode($res2, true);
        $this->assertEquals(200, $data2["code"]);
        $this->assertStringContainsString("idempotent", strtolower((string) $data2["message"]));
    }

    public function testJsonLoggerCorrelationTracing(): void
    {
        $logger = new JsonLogger("corr-test-999");
        $this->assertEquals("corr-test-999", $logger->getCorrelationId());

        $logger->info("Processing Audatex claim", ["caseId" => "NXC-100"]);
        $logger->error("Supplier connection timeout", ["supplier" => "Alpha"]);

        $logs = $logger->getLogs();
        $this->assertCount(2, $logs);
        $this->assertEquals("INFO", $logs[0]["level"]);
        $this->assertEquals("corr-test-999", $logs[0]["correlationId"]);
        $this->assertEquals("NXC-100", $logs[0]["context"]["caseId"]);
    }

    public function testIngestAudatexHandlerHandlesPartialOrMalformedData(): void
    {
        $handler = new IngestAudatexClaimHandler();

        // Feed partial document missing parts or metadata
        $case = $handler->handle("NXC-FUZZ-1", []);
        $this->assertEquals("NXC-FUZZ-1", $case->getCaseId());
        $this->assertEquals("VEHICLE_IDENTIFIED", $case->getStatus());
        $this->assertGreaterThanOrEqual(1, $case->getVersion());
    }
}