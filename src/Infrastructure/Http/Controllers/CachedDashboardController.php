<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Infrastructure\Cache\CacheInterface;

final class CachedDashboardController
{
    private DashboardController $innerController;
    private CacheInterface $cache;
    private int $defaultTtl;

    public function __construct(
        DashboardController $innerController,
        CacheInterface $cache,
        int $defaultTtl = 60
    ) {
        $this->innerController = $innerController;
        $this->cache = $cache;
        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Returns cached dashboard metrics or queries inner controller on cache miss.
     *
     * @return string JSON response
     */
    public function getExecutiveSummary(): string
    {
        $cacheKey = "nap_dashboard_summary";

        /** @var string|null $cachedResponse */
        $cachedResponse = $this->cache->get($cacheKey);
        if (is_string($cachedResponse)) {
            return $cachedResponse;
        }

        $freshResponse = $this->innerController->getExecutiveSummary();
        $this->cache->set($cacheKey, $freshResponse, $this->defaultTtl);

        return $freshResponse;
    }

    /**
     * Forces invalidation of the dashboard analytics cache.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete("nap_dashboard_summary");
    }
}