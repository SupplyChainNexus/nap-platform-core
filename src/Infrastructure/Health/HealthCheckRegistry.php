<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Health;

use NAP\Infrastructure\Cache\CacheInterface;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

final class HealthCheckRegistry
{
    private DatabaseAdapter $db;
    private ?CacheInterface $cache;

    public function __construct(DatabaseAdapter $db, ?CacheInterface $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Liveness check — returns true if the PHP runtime is responding.
     */
    public function isLive(): bool
    {
        return true;
    }

    /**
     * Readiness check — verifies database connectivity and core storage access.
     *
     * @return array<string, mixed> Detailed diagnostic status
     */
    public function checkReadiness(): array
    {
        $dbStatus = false;
        $dbLatencyMs = 0.0;
        $eventStoreCount = 0;

        try {
            $startTime = microtime(true);
            $pdo = $this->db->getPdo();
            $stmt = $pdo->query("SELECT COUNT(*) FROM nx_event_store");
            $dbLatencyMs = round((microtime(true) - $startTime) * 1000, 2);

            if ($stmt !== false) {
                $eventStoreCount = (int) $stmt->fetchColumn();
                $dbStatus = true;
            }
        } catch (\Throwable $e) {
            $dbStatus = false;
        }

        $isReady = $dbStatus;

        return [
            "status" => $isReady ? "UP" : "DOWN",
            "timestamp" => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            "checks" => [
                "database" => [
                    "status" => $dbStatus ? "UP" : "DOWN",
                    "latencyMs" => $dbLatencyMs,
                    "totalEventsInStore" => $eventStoreCount
                ],
                "cache" => [
                    "status" => $this->cache !== null ? "UP" : "DISABLED",
                    "driver" => $this->cache !== null ? get_class($this->cache) : "None"
                ]
            ]
        ];
    }
}