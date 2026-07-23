<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

use PDO;

final readonly class HealthCheckController
{
    public function __construct(
        private PDO $pdo
    ) {}

    /**
     * @return array{status: string, timestamp: string, checks: array<string, mixed>}
     */
    public function check(): array
    {
        $dbOk = false;
        try {
            $this->pdo->query("SELECT 1");
            $dbOk = true;
        } catch (\Throwable $e) {}

        $outboxLag = 0;
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM outbox_messages WHERE published_at IS NULL");
            $outboxLag = $stmt ? (int) $stmt->fetchColumn() : 0;
        } catch (\Throwable $e) {}

        $writable = is_writable(__DIR__ . "/../../../var/logs") || is_writable(sys_get_temp_dir());
        $isHealthy = $dbOk && $writable;

        return [
            "status" => $isHealthy ? "healthy" : "unhealthy",
            "timestamp" => date("c"),
            "checks" => [
                "database" => $dbOk ? "ok" : "error",
                "storage_writable" => $writable ? "ok" : "error",
                "outbox_lag" => $outboxLag,
            ],
        ];
    }
}
