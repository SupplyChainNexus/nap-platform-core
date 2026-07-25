<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Infrastructure\Http\JsonResponse;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

final class DashboardController
{
    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    public function getExecutiveSummary(): string
    {
        $pdo = $this->db->getPdo();
        
        $metrics = [
            "total_savings_amount" => 0.0,
            "total_benchmarked_quotes" => 0.0,
            "total_purchase_orders_issued" => 0.0,
            "total_parts_normalized" => 0.0
        ];

        try {
            $stmt = $pdo->query("SELECT metric_key, metric_value FROM nx_analytics_summary");
            /** @var array<int, array<string, mixed>>|false $rows */
            $rows = $stmt ? $stmt->fetchAll() : false;

            if ($rows !== false) {
                foreach ($rows as $row) {
                    $key = is_string($row["metric_key"] ?? null) ? $row["metric_key"] : "";
                    $valRaw = $row["metric_value"] ?? null;
                    if (array_key_exists($key, $metrics) && is_numeric($valRaw)) {
                        $metrics[$key] = (float) $valRaw;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Return zeroed metrics if analytics table has not been initialized yet
        }

        return JsonResponse::create([
            "totalSavingsAmount" => $metrics["total_savings_amount"],
            "benchmarkedQuotesCount" => (int) $metrics["total_benchmarked_quotes"],
            "purchaseOrdersIssuedCount" => (int) $metrics["total_purchase_orders_issued"],
            "partsNormalizedCount" => (int) $metrics["total_parts_normalized"],
            "currency" => "ZAR"
        ], 200);
    }
}