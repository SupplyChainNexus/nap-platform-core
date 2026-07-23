<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Infrastructure\Persistence\OutboxPublisher;
use PDO;
use PHPUnit\Framework\TestCase;

final class OutboxPublisherTest extends TestCase
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

    public function testEnqueuesAndPublishesOutboxMessages(): void
    {
        $publisher = new OutboxPublisher($this->pdo);
        $publisher->enqueue("CaseCreatedEvent", ["case_id" => "018e38f9-472b-7b33-8a30-89196b0521e1"], "2026-07-23 12:00:00");

        $dispatchedEvents = [];
        $count = $publisher->processPending(function (string $eventType, array $payload) use (&$dispatchedEvents): void {
            $dispatchedEvents[] = ["type" => $eventType, "payload" => $payload];
        });

        $this->assertSame(1, $count);
        $this->assertCount(1, $dispatchedEvents);
        $this->assertSame("CaseCreatedEvent", $dispatchedEvents[0]["type"]);

        // Ensure second run finds 0 pending
        $secondCount = $publisher->processPending(function (): void {});
        $this->assertSame(0, $secondCount);
    }
}
