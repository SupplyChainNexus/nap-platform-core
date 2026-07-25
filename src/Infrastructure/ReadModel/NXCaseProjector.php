<?php

declare(strict_types=1);

namespace NAP\Infrastructure\ReadModel;

use NAP\Domain\Events\DomainEventInterface;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use PDO;

final class NXCaseProjector implements ProjectionInterface
{
    private PDO $pdo;
    private string $driver;

    public function __construct(?DatabaseAdapter $dbAdapter = null)
    {
        $adapter = $dbAdapter ?? new DatabaseAdapter();
        $this->pdo = $adapter->getPdo();
        $this->driver = $adapter->getDriver();
        $this->initializeProjectionSchema();
    }

    private function initializeProjectionSchema(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS nx_case_read_model (
            case_id VARCHAR(128) PRIMARY KEY,
            claim_number VARCHAR(128) NOT NULL,
            insurer VARCHAR(128) NOT NULL,
            insured VARCHAR(128) NOT NULL,
            repairer VARCHAR(128) NOT NULL DEFAULT '',
            vin VARCHAR(128) NOT NULL DEFAULT '',
            make VARCHAR(128) NOT NULL DEFAULT '',
            model VARCHAR(128) NOT NULL DEFAULT '',
            status VARCHAR(64) NOT NULL,
            total_repair_cost DOUBLE PRECISION NOT NULL DEFAULT 0.0,
            last_updated VARCHAR(64) NOT NULL
        )";

        $this->pdo->exec($sql);
    }

    public function project(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $caseId = $event->getAggregateId();
        $occurred = $event->getOccurredAt()->format("Y-m-d H:i:s");

        switch ($event->getEventName()) {
            case "AuthorizationReceived":
                $stmt = $this->pdo->prepare("INSERT INTO nx_case_read_model (case_id, claim_number, insurer, insured, status, last_updated)
                    VALUES (:case_id, :claim_num, :insurer, :insured, 'AUTHORIZED', :updated)
                    ON CONFLICT(case_id) DO UPDATE SET status = 'AUTHORIZED', last_updated = EXCLUDED.last_updated");
                
                // Fallback for SQLite single insert/update compatibility
                if ($this->driver === "sqlite") {
                    $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO nx_case_read_model (case_id, claim_number, insurer, insured, status, last_updated)
                        VALUES (:case_id, :claim_num, :insurer, :insured, 'AUTHORIZED', :updated)");
                }

                $stmt->execute([
                    ":case_id" => $caseId,
                    ":claim_num" => is_string($payload["claimNumber"] ?? null) ? $payload["claimNumber"] : "",
                    ":insurer" => is_string($payload["insurer"] ?? null) ? $payload["insurer"] : "",
                    ":insured" => is_string($payload["insured"] ?? null) ? $payload["insured"] : "",
                    ":updated" => $occurred
                ]);
                break;

            case "ClaimAssessmentIngested":
                $stmt = $this->pdo->prepare("UPDATE nx_case_read_model SET repairer = :repairer, total_repair_cost = :cost, status = 'ASSESSMENT_INGESTED', last_updated = :updated WHERE case_id = :case_id");
                $stmt->execute([
                    ":repairer" => is_string($payload["repairer"] ?? null) ? $payload["repairer"] : "",
                    ":cost" => is_numeric($payload["totalRepairCostExclVat"] ?? null) ? (float) $payload["totalRepairCostExclVat"] : 0.0,
                    ":updated" => $occurred,
                    ":case_id" => $caseId
                ]);
                break;

            case "VehicleIdentified":
                $stmt = $this->pdo->prepare("UPDATE nx_case_read_model SET vin = :vin, make = :make, model = :model, status = 'VEHICLE_IDENTIFIED', last_updated = :updated WHERE case_id = :case_id");
                $stmt->execute([
                    ":vin" => is_string($payload["vin"] ?? null) ? $payload["vin"] : "",
                    ":make" => is_string($payload["make"] ?? null) ? $payload["make"] : "",
                    ":model" => is_string($payload["model"] ?? null) ? $payload["model"] : "",
                    ":updated" => $occurred,
                    ":case_id" => $caseId
                ]);
                break;
        }
    }

    public function reset(): void
    {
        $this->pdo->exec("DELETE FROM nx_case_read_model");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getReadModel(string $caseId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM nx_case_read_model WHERE case_id = :case_id");
        $stmt->execute([":case_id" => $caseId]);
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }
}
