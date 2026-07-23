<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Infrastructure\Persistence\DatabaseEventStore;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseEventStoreTest extends TestCase
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
        ");
    }

    public function testAppendAndRetrieveEvents(): void
    {
        $store = new DatabaseEventStore($this->pdo);
        $store->append("CASE-100", "CaseCreatedEvent", ["context" => "Fleet Procurement"], "2026-07-23 12:00:00");

        $events = $store->getEventsForAggregate("CASE-100");

        $this->assertCount(1, $events);
        $this->assertSame("CASE-100", $events[0]["aggregate_id"]);
        $this->assertSame("CaseCreatedEvent", $events[0]["event_type"]);
    }
}
