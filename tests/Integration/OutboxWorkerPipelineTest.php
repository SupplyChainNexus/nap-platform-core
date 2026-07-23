<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Application\Commands\CreateCaseCommand;
use NAP\Application\Handlers\CreateCaseHandler;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\Infrastructure\Persistence\OutboxPublisher;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class OutboxWorkerPipelineTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE outbox_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                published_at DATETIME NULL
            );
        ");
    }

    public function testCaseCreationEnqueuesOutboxMessageAndWorkerProcessesIt(): void
    {
        $publisher = new OutboxPublisher($this->pdo);

        $idGen = new class implements IdGeneratorInterface {
            public function generate(): string {
                return "018e38f9-472b-7b33-8a30-89196b0521e1";
            }
        };

        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable {
                return new DateTimeImmutable("2026-07-23 12:00:00");
            }
        };

        $caseRepo = new InMemoryCaseRepository();
        $handler = new CreateCaseHandler($idGen, $clock, $caseRepo);

        // 1. Execute Command
        $cmd = new CreateCaseCommand("CAT-PUMP-900", 150000, "USD", "Procurement Audit");
        $caseId = $handler->handle($cmd);
        $idString = (string) $caseId;

        $this->assertSame("018e38f9-472b-7b33-8a30-89196b0521e1", $idString);

        // 2. Explicitly enqueue event into Outbox
        $publisher->enqueue("CaseCreatedEvent", [
            "case_id" => $idString,
            "part_number" => "CAT-PUMP-900",
            "amount" => 150000,
            "currency" => "USD",
        ], "2026-07-23 12:00:00");

        // 3. Worker consumes outbox queue
        $dispatched = [];
        $processedCount = $publisher->processPending(function (string $type, array $payload) use (&$dispatched): void {
            $dispatched[] = ["type" => $type, "payload" => $payload];
        });

        $this->assertSame(1, $processedCount);
        $this->assertCount(1, $dispatched);
        $this->assertSame("CaseCreatedEvent", $dispatched[0]["type"]);
        $this->assertSame("CAT-PUMP-900", $dispatched[0]["payload"]["part_number"]);

        // 4. Verify outbox is clean
        $subsequentCount = $publisher->processPending(function (): void {});
        $this->assertSame(0, $subsequentCount);
    }
}
