<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Console;

use NAP\Domain\Events\AbstractDomainEvent;
use NAP\Infrastructure\Cache\CacheInterface;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\ReadModel\AnalyticsProjector;

final class ConsoleApplication
{
    private DatabaseAdapter $db;
    private ?CacheInterface $cache;

    public function __construct(DatabaseAdapter $db, ?CacheInterface $cache = null)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * Executes a console command based on CLI arguments.
     *
     * @param array<int, string> $argv
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? "help";

        return match ($command) {
            "migrate" => $this->runMigrate(),
            "projections:rebuild" => $this->runRebuildProjections(),
            "cache:clear" => $this->runClearCache(),
            default => $this->runHelp()
        };
    }

    private function runMigrate(): int
    {
        try {
            $pdo = $this->db->getPdo();
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS nx_event_store (
                    event_id TEXT PRIMARY KEY,
                    stream_id TEXT NOT NULL,
                    event_type TEXT NOT NULL,
                    payload TEXT NOT NULL,
                    version INTEGER NOT NULL,
                    recorded_at TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS nx_cases (
                    case_id TEXT PRIMARY KEY,
                    claim_number TEXT NOT NULL,
                    status TEXT NOT NULL,
                    version INTEGER NOT NULL,
                    updated_at TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS nx_analytics_summary (
                    metric_key TEXT PRIMARY KEY,
                    metric_value REAL NOT NULL,
                    last_updated TEXT NOT NULL
                );

                CREATE TABLE IF NOT EXISTS nx_processed_webhooks (
                    idempotency_key TEXT PRIMARY KEY,
                    processed_at TEXT NOT NULL
                );
            ");

            echo "[SUCCESS] Database schemas migrated successfully.\n";
            return 0;
        } catch (\Throwable $e) {
            echo "[ERROR] Migration failed: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    private function runRebuildProjections(): int
    {
        try {
            $pdo = $this->db->getPdo();
            $stmt = $pdo->query("SELECT event_type, payload FROM nx_event_store ORDER BY version ASC");
            if ($stmt === false) {
                echo "[WARNING] Event store table empty or uninitialized.\n";
                return 0;
            }

            $projector = new AnalyticsProjector($this->db);
            $replayedCount = 0;

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if (is_array($row) && is_string($row["payload"])) {
                    /** @var array<string, mixed> $payload */
                    $payload = json_decode($row["payload"], true) ?? [];
                    $eventType = is_string($row["event_type"] ?? null) ? $row["event_type"] : "UnknownEvent";

                    $domainEvent = new class($eventType, $payload) extends AbstractDomainEvent {
                        private string $name;

                        /** @param array<string, mixed> $payload */
                        public function __construct(string $name, array $payload)
                        {
                            parent::__construct("stream-rebuild", $payload);
                            $this->name = $name;
                        }

                        public function getEventName(): string
                        {
                            return $this->name;
                        }
                    };

                    $projector->project($domainEvent);
                    $replayedCount++;
                }
            }

            echo "[SUCCESS] Rebuilt read projections from {$replayedCount} stored events.\n";
            return 0;
        } catch (\Throwable $e) {
            echo "[ERROR] Projection rebuild failed: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    private function runClearCache(): int
    {
        if ($this->cache === null) {
            echo "[INFO] No cache driver configured.\n";
            return 0;
        }

        $this->cache->clear();
        echo "[SUCCESS] Operational cache cleared successfully.\n";
        return 0;
    }

    private function runHelp(): int
    {
        echo "NAP Platform Core CLI Driver\n";
        echo "Available commands:\n";
        echo "  migrate              - Run database schema migrations\n";
        echo "  projections:rebuild  - Replay event store to rebuild CQRS read models\n";
        echo "  cache:clear          - Flush active cache store\n";
        return 0;
    }
}