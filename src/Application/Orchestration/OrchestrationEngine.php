<?php

declare(strict_types=1);

namespace NAP\Application\Orchestration;

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Domain\Agents\SupplierRiskAgent;
use NAP\Infrastructure\Agents\GeminiAgentAdapter;

final class OrchestrationEngine
{
    private GeminiAgentAdapter $pricingAgent;
    private SupplierRiskAgent $riskAgent;

    public function __construct(?GeminiAgentAdapter $pricingAgent = null, ?SupplierRiskAgent $riskAgent = null)
    {
        $apiKey = getenv("GEMINI_API_KEY") ?: "";
        $model = getenv("GEMINI_MODEL") ?: "gemini-2.5-flash";

        $this->pricingAgent = $pricingAgent ?? new GeminiAgentAdapter($apiKey, $model);
        $this->riskAgent = $riskAgent ?? new SupplierRiskAgent();
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluatePurchaseOrder(string $partNumber, float $originalAmount, string $supplierId): array
    {
        $context = new PromptContext("procurement_evaluation", [
            "partNumber" => $partNumber,
            "normalizedAmount" => $originalAmount,
            "supplierId" => $supplierId
        ]);
        $pricingResult = $this->pricingAgent->generateStructuredOutput($context);

        $riskResult = $this->riskAgent->evaluateSupplierRisk($supplierId, $originalAmount);

        $rawConf = $pricingResult["confidence"] ?? 0.5;
        $confidence = is_numeric($rawConf) ? (float) $rawConf : 0.5;

        $rawRiskScore = $riskResult["riskScore"] ?? 0.5;
        $riskScore = is_numeric($rawRiskScore) ? (float) $rawRiskScore : 0.5;

        $decision = match (true) {
            $riskScore > 0.50 || $confidence < 0.40 => "HUMAN_REVIEW_REQUIRED",
            $originalAmount > 250000.0 => "ESCALATE_TO_BOARD",
            default => "AUTO_APPROVE"
        };

        /** @var array<int, string> $pricingReasons */
        $pricingReasons = is_array($pricingResult["reasons"] ?? null) ? $pricingResult["reasons"] : [];
        /** @var array<int, string> $riskReasons */
        $riskReasons = is_array($riskResult["reasons"] ?? null) ? $riskResult["reasons"] : [];

        $allReasons = array_merge($pricingReasons, $riskReasons);

        $rawRec = $pricingResult["recommendedAmount"] ?? $originalAmount;
        $recommendedVal = is_numeric($rawRec) ? (float) $rawRec : $originalAmount;

        $riskTier = is_string($riskResult["riskTier"] ?? null) ? $riskResult["riskTier"] : "UNKNOWN";

        return [
            "decision" => $decision,
            "partNumber" => $partNumber,
            "supplierId" => strtoupper($supplierId),
            "originalAmount" => (int) $originalAmount,
            "recommendedAmount" => (int) round($recommendedVal),
            "savingsAmount" => (int) max(0, $originalAmount - $recommendedVal),
            "confidence" => $confidence,
            "riskTier" => $riskTier,
            "riskScore" => $riskScore,
            "reasons" => $allReasons,
            "currency" => "ZAR"
        ];
    }
}
