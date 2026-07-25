<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Cache\ArrayCacheDriver;
use NAP\Infrastructure\Console\BenchmarkRunner;
use NAP\Infrastructure\Console\ConsoleApplication;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use PHPUnit\Framework\TestCase;

final class BenchmarkRunnerTest extends TestCase
{
    public function testBenchmarkSuiteExecutesSuccessfully(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);
        $app = new ConsoleApplication($db);

        // Migrate first
        $app->run(["bin/nap", "migrate"]);

        $cache = new ArrayCacheDriver();
        $runner = new BenchmarkRunner($db, $cache);

        $results = $runner->runSuite(100);

        $this->assertEquals(100, $results["iterations"]);
        $this->assertArrayHasKey("eventStoreWrite", $results);
        $this->assertArrayHasKey("projectionReplay", $results);
        $this->assertArrayHasKey("cacheLayer", $results);
        $this->assertGreaterThan(0, $results["eventStoreWrite"]["opsPerSec"]);
    }
}