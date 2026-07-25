<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Cache\ArrayCacheDriver;
use NAP\Infrastructure\Health\HealthCheckRegistry;
use NAP\Infrastructure\Http\Controllers\HealthController;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

use PHPUnit\Framework\TestCase;

final class HealthCheckTest extends TestCase
{
    public function testLivenessProbeReturnsOk(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);
        $registry = new HealthCheckRegistry($db);
        $controller = new HealthController($registry);

        $response = $controller->getLiveness();
        /** @var array<string, mixed> $responseArray */
        $responseArray = json_decode($response, true);

        $this->assertEquals("success", $responseArray["status"]);
        
        /** @var array<string, mixed> $data */
        $data = $responseArray["data"];
        $this->assertEquals("UP", $data["status"]);
        $this->assertEquals("nap-platform-core", $data["service"]);
    }

    public function testReadinessProbeReturnsHealthyDiagnostics(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $pdo->exec("
            CREATE TABLE nx_event_store (
                event_id TEXT PRIMARY KEY
            );
        ");

        $db = new DatabaseAdapter($pdo);
        $cache = new ArrayCacheDriver();
        $registry = new HealthCheckRegistry($db, $cache);
        $controller = new HealthController($registry);

        $response = $controller->getReadiness();
        /** @var array<string, mixed> $responseArray */
        $responseArray = json_decode($response, true);

        $this->assertEquals("success", $responseArray["status"]);

        /** @var array<string, mixed> $data */
        $data = $responseArray["data"];
        $this->assertEquals("UP", $data["status"]);
        $this->assertEquals("UP", $data["checks"]["database"]["status"]);
        $this->assertEquals("UP", $data["checks"]["cache"]["status"]);
        $this->assertEquals(0, $data["checks"]["database"]["totalEventsInStore"]);
    }
}