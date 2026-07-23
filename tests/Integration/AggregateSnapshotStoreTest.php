<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Infrastructure\Persistence\AggregateSnapshotStore;
use PDO;
use PHPUnit\Framework\TestCase;

final class AggregateSnapshotStoreTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE case_snapshots (
                aggregate_id TEXT PRIMARY KEY,
                version INTEGER NOT NULL,
                state TEXT NOT NULL,
                created_at DATETIME NOT NULL
            );
        ");
    }

    public function testSavesAndRetrievesSnapshot(): void
    {
        $store = new AggregateSnapshotStore($this->pdo);
        $store->saveSnapshot("CASE-100", 5, ["status" => "APPROVED", "amount" => 15000]);

        $snapshot = $store->getSnapshot("CASE-100");

        $this->assertNotNull($snapshot);
        $this->assertSame("CASE-100", $snapshot["aggregate_id"]);
        $this->assertSame(5, $snapshot["version"]);
        $this->assertSame("APPROVED", $snapshot["state"]["status"]);
    }
}
