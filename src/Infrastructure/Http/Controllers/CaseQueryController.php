<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Infrastructure\Http\JsonResponse;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

final class CaseQueryController
{
    private DatabaseAdapter $db;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
    }

    public function getCaseDetails(string $caseId): string
    {
        $pdo = $this->db->getPdo();
        $stmt = $pdo->prepare("SELECT * FROM nx_case_read_model WHERE case_id = :caseId");
        $stmt->execute([":caseId" => $caseId]);
        
        /** @var array<string, mixed>|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return JsonResponse::create(["message" => "Case not found for ID: {$caseId}"], 404);
        }

        $caseIdVal = is_string($row["case_id"] ?? null) ? $row["case_id"] : "";
        $claimNumberVal = is_string($row["claim_number"] ?? null) ? $row["claim_number"] : "";
        $insurerVal = is_string($row["insurer"] ?? null) ? $row["insurer"] : "";
        $repairerVal = is_string($row["repairer"] ?? null) ? $row["repairer"] : "";
        $vinVal = is_string($row["vin"] ?? null) ? $row["vin"] : "";
        
        $makeRaw = $row["make"] ?? $row["vehicle_make"] ?? null;
        $makeVal = is_string($makeRaw) ? $makeRaw : "";

        $modelRaw = $row["model"] ?? $row["vehicle_model"] ?? null;
        $modelVal = is_string($modelRaw) ? $modelRaw : "";

        $yearVal = is_numeric($row["vehicle_year"] ?? null) ? (int) $row["vehicle_year"] : 0;
        $statusVal = is_string($row["status"] ?? null) ? $row["status"] : "";
        
        $costRaw = $row["total_repair_cost_excl_vat"] ?? $row["total_repair_cost"] ?? null;
        $costVal = is_numeric($costRaw) ? (float) $costRaw : 0.0;
        
        $versionVal = is_numeric($row["version"] ?? null) ? (int) $row["version"] : 1;

        return JsonResponse::create([
            "caseId" => $caseIdVal,
            "claimNumber" => $claimNumberVal,
            "insurer" => $insurerVal,
            "repairer" => $repairerVal,
            "vin" => $vinVal,
            "vehicleMake" => $makeVal,
            "vehicleModel" => $modelVal,
            "vehicleYear" => $yearVal,
            "status" => $statusVal,
            "totalRepairCostExclVat" => $costVal,
            "version" => $versionVal
        ], 200);
    }
}