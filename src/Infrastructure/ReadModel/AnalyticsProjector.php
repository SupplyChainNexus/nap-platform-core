<?php

declare(strict_types=1);

namespace NAP\Infrastructure\ReadModel;

use NAP\Domain\Events\AbstractDomainEvent;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

final class AnalyticsProjector
{
    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    public function ensureSchemaExists(): void
    {
        $pdo = $this->db->getPdo();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS nx_analytics_summary (
                metric_key VARCHAR(64) PRIMARY KEY,
                metric_value DECIMAL(12,2),
                last_updated VARCHAR(32)
            );
        ");
    }

    public function project(AbstractDomainEvent $event): void
    {
        $this->ensureSchemaExists();
        $eventName = $event->getEventName();
        $payload = $event->getPayload();

        if ($eventName === "PriceBenchmarked") {
            $savings = is_numeric($payload["savingsAmount"] ?? null) ? (float) $payload["savingsAmount"] : 0.0;
            $this->incrementMetric("total_savings_amount", $savings);
            $this->incrementMetric("total_benchmarked_quotes", 1.0);
        } elseif ($eventName === "PurchaseOrderIssued") {
            $this->incrementMetric("total_purchase_orders_issued", 1.0);
        } elseif ($eventName === "PartNormalized") {
            $this->incrementMetric("total_parts_normalized", 1.0);
        }
    }

    private function incrementMetric(string $key, float $increment): void
    {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO nx_analytics_summary (metric_key, metric_value, last_updated)
            VALUES (:key, :val, :updated)
            ON CONFLICT(metric_key) DO UPDATE SET
                metric_value = metric_value + :val,
                last_updated = :updated
        ");

        $stmt->execute([
            ":key" => $key,
            ":val" => $increment,
            ":updated" => date("Y-m-d H:i:s")
        ]);
    }
}
