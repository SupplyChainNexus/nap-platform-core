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

    public function isLive(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function checkReadiness(): array
    {
        $dbStatus = false;
        $dbLatencyMs = 0.0;
        $eventStoreCount = 0;

        try {
            $startTime = microtime(true);
            $pdo = $this->db->getPdo();

            // Resilient DB ping
            $pdo->query("SELECT 1");

            // Check event store count safely
            try {
                $stmt = $pdo->query("SELECT COUNT(*) FROM nx_event_store");
                if ($stmt !== false) {
                    $eventStoreCount = (int) $stmt->fetchColumn();
                }
            } catch (\Throwable $e) {
                // Table doesn't exist yet - initialize schema
                $pdo->exec("CREATE TABLE IF NOT EXISTS nx_event_store (
                    event_id VARCHAR(64) PRIMARY KEY,
                    event_type VARCHAR(128) NOT NULL,
                    payload TEXT NOT NULL,
                    created_at VARCHAR(64) NOT NULL
                )");
            }

            $dbLatencyMs = round((microtime(true) - $startTime) * 1000, 2);
            $dbStatus = true;
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
