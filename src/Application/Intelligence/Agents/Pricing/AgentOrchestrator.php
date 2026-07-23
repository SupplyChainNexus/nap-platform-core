<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Agents\Pricing;

use NAP\Application\Intelligence\DTO\PricingRecommendation;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;

final readonly class AgentOrchestrator
{
    public function __construct(
        private PricingIntelligenceAgent $pricingAgent,
        private HistoricalAuditAgent $auditAgent,
        private SupplierReputationAgent $reputationAgent
    ) {}

    /**
     * @return array{
     *     part_number: string,
     *     supplier_id: string,
     *     recommended_price_cents: int,
     *     currency: string,
     *     overall_confidence: float,
     *     reputation_score: float,
     *     risk_flags: list<string>,
     *     all_reasons: list<string>
     * }
     */
    public function evaluate(string $partNumber, string $supplierId, NAPMoney $quote): array
    {
        $pricingRec = $this->pricingAgent->analyzePricing($partNumber, $quote, "ZAR");
        $auditRes = $this->auditAgent->auditHistory($partNumber);
        $repRes = $this->reputationAgent->evaluateSupplier($supplierId);

        $baseCents = $pricingRec->recommendedPrice->getAmountInCents();
        $adjustedCents = (int) round($baseCents * $auditRes["historical_factor"]);

        $combinedConfidence = round(($pricingRec->confidenceScore + $repRes["reputation_score"]) / 2, 2);
        $allReasons = array_values(array_unique(array_merge($pricingRec->reasoningFactors, $auditRes["reasoning"])));

        return [
            "part_number" => $partNumber,
            "supplier_id" => $supplierId,
            "recommended_price_cents" => $adjustedCents,
            "currency" => "ZAR",
            "overall_confidence" => $combinedConfidence,
            "reputation_score" => $repRes["reputation_score"],
            "risk_flags" => $repRes["risk_flags"],
            "all_reasons" => $allReasons,
        ];
    }
}
