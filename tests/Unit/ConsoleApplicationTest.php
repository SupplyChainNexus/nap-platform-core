<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Cache\ArrayCacheDriver;
use NAP\Infrastructure\Console\ConsoleApplication;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

use PHPUnit\Framework\TestCase;

final class ConsoleApplicationTest extends TestCase
{
    public function testMigrateCommandCreatesTables(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);
        $app = new ConsoleApplication($db);

        ob_start();
        $exitCode = $app->run(["bin/nap", "migrate"]);
        $output = ob_get_clean();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString("SUCCESS", (string) $output);

        // Verify tables exist
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='nx_event_store'");
        $this->assertNotFalse($stmt);
        $this->assertEquals("nx_event_store", $stmt->fetchColumn());
    }

    public function testRebuildProjectionsAndClearCache(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);
        $cache = new ArrayCacheDriver();
        $cache->set("test_key", "test_val", 300);

        $app = new ConsoleApplication($db, $cache);

        // Run migrations first
        $app->run(["bin/nap", "migrate"]);

        // Clear cache
        ob_start();
        $exitCodeCache = $app->run(["bin/nap", "cache:clear"]);
        $outputCache = ob_get_clean();

        $this->assertEquals(0, $exitCodeCache);
        $this->assertStringContainsString("cleared", (string) $outputCache);
        $this->assertNull($cache->get("test_key"));

        // Rebuild projections
        ob_start();
        $exitCodeProj = $app->run(["bin/nap", "projections:rebuild"]);
        $outputProj = ob_get_clean();

        $this->assertEquals(0, $exitCodeProj);
        $this->assertStringContainsString("Rebuilt read projections", (string) $outputProj);
    }
}