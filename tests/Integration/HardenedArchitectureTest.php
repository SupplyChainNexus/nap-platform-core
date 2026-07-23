<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Infrastructure\Persistence\DatabaseEventStore;
use NAP\Infrastructure\Persistence\OutboxPublisher;
use NAP\SharedKernel\Domain\Exceptions\ConcurrencyException;
use PDO;
use PHPUnit\Framework\TestCase;

final class HardenedArchitectureTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE domain_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                aggregate_id TEXT NOT NULL,
                event_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                occurred_at DATETIME NOT NULL
            );
            CREATE TABLE outbox_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                published_at DATETIME NULL
            );
        ");
    }

    public function testOptimisticLockingDetectsConcurrencyConflict(): void
    {
        $store = new DatabaseEventStore($this->pdo);
        $store->append("CASE-99", "CaseCreated", ["a" => 1], "2026-07-23 10:00:00");

        $this->expectException(ConcurrencyException::class);
        $this->expectExceptionMessage("Concurrency conflict for aggregate 'CASE-99': expected version 0, but current version is 1.");

        $store->append("CASE-99", "CaseUpdated", ["a" => 2], "2026-07-23 10:01:00", 0);
    }

    public function testOutboxInjectsIdempotencyMetadata(): void
    {
        $publisher = new OutboxPublisher($this->pdo);
        $publisher->enqueue("OrderPlaced", ["order_id" => "ORD-123"], "2026-07-23 10:00:00");

        $dispatched = [];
        $publisher->processPending(function (string $type, array $payload) use (&$dispatched): void {
            $dispatched[] = $payload;
        });

        $this->assertCount(1, $dispatched);
        $this->assertArrayHasKey("_metadata", $dispatched[0]);
        /** @var array{message_id: string, idempotency_key: string} $metadata */
        $metadata = $dispatched[0]["_metadata"];
        $this->assertStringStartsWith("msg_", $metadata["message_id"]);
        $this->assertSame($metadata["message_id"], $metadata["idempotency_key"]);
    }
}
