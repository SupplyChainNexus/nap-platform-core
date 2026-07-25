<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Console;

use NAP\Domain\Events\AbstractDomainEvent;
use NAP\Infrastructure\Cache\CacheInterface;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\AnalyticsProjector;

final class BenchmarkRunner
{
    private DatabaseAdapter $db;
    private CacheInterface $cache;

    public function __construct(DatabaseAdapter $db, CacheInterface $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Executes the performance benchmark suite.
     *
     * @param int $iterations Total event operations to simulate
     * @return array<string, mixed> Performance results report
     */
    public function runSuite(int $iterations = 1000): array
    {
        $pdo = $this->db->getPdo();

        // 1. Benchmark Event Store Write Throughput
        $startTime = microtime(true);
        $stmt = $pdo->prepare("
            INSERT INTO nx_event_store (event_id, stream_id, event_type, payload, version, recorded_at)
            VALUES (:id, :stream, :type, :payload, :version, :recorded_at)
        ");

        $pdo->beginTransaction();
        for ($i = 1; $i <= $iterations; $i++) {
            $stmt->execute([
                ":id" => "evt-bench-" . $i,
                ":stream" => "stream-bench-" . ($i % 10),
                ":type" => "PriceBenchmarked",
                ":payload" => (string) json_encode(["savingsAmount" => 250.00, "quoteId" => "Q-" . $i]),
                ":version" => $i,
                ":recorded_at" => date("c")
            ]);
        }
        $pdo->commit();
        $writeDuration = microtime(true) - $startTime;
        $writeOpsPerSec = $iterations / max($writeDuration, 0.0001);

        // 2. Benchmark CQRS Read Projection Replay
        $startReplay = microtime(true);
        $projector = new AnalyticsProjector($this->db);
        $queryStmt = $pdo->query("SELECT event_type, payload FROM nx_event_store ORDER BY version ASC");
        
        $replayed = 0;
        if ($queryStmt !== false) {
            while ($row = $queryStmt->fetch(\PDO::FETCH_ASSOC)) {
                if (is_array($row) && is_string($row["payload"])) {
                    /** @var array<string, mixed> $payload */
                    $payload = json_decode($row["payload"], true) ?? [];
                    $eventType = is_string($row["event_type"] ?? null) ? $row["event_type"] : "UnknownEvent";

                    $event = new class($eventType, $payload) extends AbstractDomainEvent {
                        private string $name;

                        /** @param array<string, mixed> $payload */
                        public function __construct(string $name, array $payload)
                        {
                            parent::__construct("stream-bench", $payload);
                            $this->name = $name;
                        }

                        public function getEventName(): string
                        {
                            return $this->name;
                        }
                    };

                    $projector->project($event);
                    $replayed++;
                }
            }
        }
        $replayDuration = microtime(true) - $startReplay;
        $replayOpsPerSec = $replayed / max($replayDuration, 0.0001);

        // 3. Benchmark Cache Read/Write Throughput
        $startCache = microtime(true);
        for ($c = 1; $c <= $iterations; $c++) {
            $this->cache->set("bench_key_" . $c, "bench_val_" . $c, 60);
            $this->cache->get("bench_key_" . $c);
        }
        $cacheDuration = microtime(true) - $startCache;
        $cacheOpsPerSec = ($iterations * 2) / max($cacheDuration, 0.0001);

        return [
            "iterations" => $iterations,
            "eventStoreWrite" => [
                "durationSec" => round($writeDuration, 4),
                "opsPerSec" => round($writeOpsPerSec, 2)
            ],
            "projectionReplay" => [
                "replayedEvents" => $replayed,
                "durationSec" => round($replayDuration, 4),
                "opsPerSec" => round($replayOpsPerSec, 2)
            ],
            "cacheLayer" => [
                "durationSec" => round($cacheDuration, 4),
                "opsPerSec" => round($cacheOpsPerSec, 2)
            ]
        ];
    }
}