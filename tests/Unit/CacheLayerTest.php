<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Cache\ArrayCacheDriver;
use NAP\Infrastructure\Http\Controllers\CachedDashboardController;
use NAP\Infrastructure\Http\Controllers\DashboardController;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use PHPUnit\Framework\TestCase;

final class CacheLayerTest extends TestCase
{
    public function testArrayCacheDriverStorageAndExpiration(): void
    {
        $cache = new ArrayCacheDriver();

        $cache->set("key_1", "value_1", 300);
        $this->assertEquals("value_1", $cache->get("key_1"));
        $this->assertEquals("default", $cache->get("non_existent", "default"));

        $cache->delete("key_1");
        $this->assertNull($cache->get("key_1"));
    }

    public function testCachedDashboardControllerCachesResponse(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);

        $pdo->exec("
            CREATE TABLE nx_analytics_summary (
                metric_key TEXT PRIMARY KEY,
                metric_value REAL NOT NULL,
                updated_at TEXT NOT NULL
            );
        ");

        $innerController = new DashboardController($db);
        $cache = new ArrayCacheDriver();

        $cachedController = new CachedDashboardController($innerController, $cache, 60);

        // First call populates cache
        $response1 = $cachedController->getExecutiveSummary();
        $this->assertStringContainsString("totalSavingsAmount", $response1);
        $this->assertEquals(1, $cache->count());

        // Second call retrieves from cache directly
        $response2 = $cachedController->getExecutiveSummary();
        $this->assertEquals($response1, $response2);

        // Invalidation clears cache
        $cachedController->invalidateCache();
        $this->assertEquals(0, $cache->count());
    }
}