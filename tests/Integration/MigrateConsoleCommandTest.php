<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Infrastructure\Console\MigrateConsoleCommand;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrateConsoleCommandTest extends TestCase
{
    private PDO $pdo;
    private string $migrationsDir;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->migrationsDir = __DIR__ . "/../../resources/migrations";
    }

    public function testMigrateRunsUnappliedMigrationsSequentially(): void
    {
        $command = new MigrateConsoleCommand($this->pdo, $this->migrationsDir);
        $result = $command->execute();

        $this->assertSame("success", $result["status"]);
        $this->assertContains("001_initial_schema.sql", $result["applied"]);
        $this->assertContains("002_exchange_rates.sql", $result["applied"]);
    }

    public function testMigrateIsIdempotentOnSecondRun(): void
    {
        $command = new MigrateConsoleCommand($this->pdo, $this->migrationsDir);
        $command->execute();

        $secondRun = $command->execute();
        $this->assertSame("success", $secondRun["status"]);
        $this->assertEmpty($secondRun["applied"]);
    }
}
