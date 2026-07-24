<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use PDO;

final class EvaluationRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * @param array<string, mixed> $evaluation
     */
    public function logEvaluation(string $partNumber, string $supplierId, float $originalAmount, array $evaluation): void
    {
        $rawRecommended = $evaluation["recommendedAmount"] ?? $originalAmount;
        $recommended = is_numeric($rawRecommended) ? (float) $rawRecommended : $originalAmount;

        $rawConfidence = $evaluation["confidence"] ?? 0.50;
        $confidence = is_numeric($rawConfidence) ? (float) $rawConfidence : 0.50;

        $reasons = is_array($evaluation["reasons"] ?? null) ? json_encode($evaluation["reasons"]) : "[]";
        $savings = max(0.0, $originalAmount - $recommended);

        $stmt = $this->pdo->prepare("
            INSERT INTO evaluations_log 
            (part_number, supplier_id, original_amount, recommended_amount, savings_amount, confidence, reasons_json, created_at)
            VALUES (:part_number, :supplier_id, :original_amount, :recommended_amount, :savings_amount, :confidence, :reasons_json, :created_at)
        ");

        $stmt->execute([
            ":part_number" => $partNumber,
            ":supplier_id" => $supplierId,
            ":original_amount" => $originalAmount,
            ":recommended_amount" => $recommended,
            ":savings_amount" => $savings,
            ":confidence" => $confidence,
            ":reasons_json" => $reasons,
            ":created_at" => date("Y-m-d H:i:s")
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentEvaluations(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("
            SELECT id, part_number, supplier_id, original_amount, recommended_amount, savings_amount, confidence, reasons_json, created_at 
            FROM evaluations_log 
            ORDER BY id DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $rawJson = is_string($row["reasons_json"] ?? null) ? $row["reasons_json"] : "[]";
            $row["reasons"] = json_decode($rawJson, true) ?? [];
            unset($row["reasons_json"]);
        }

        return $rows;
    }
}
