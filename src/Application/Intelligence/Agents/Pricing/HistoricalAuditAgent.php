<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Agents\Pricing;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;

final readonly class HistoricalAuditAgent
{
    public function __construct(
        private LlmProviderInterface $llmProvider
    ) {}

    /**
     * @return array{historical_risk: string, historical_factor: float, reasoning: list<string>}
     */
    public function auditHistory(string $partNumber): array
    {
        $context = new PromptContext("historical_audit_v1", ["partNumber" => $partNumber]);
        $result = $this->llmProvider->generateStructuredOutput($context);

        $rawRisk = $result["riskLevel"] ?? null;
        $riskLevel = is_string($rawRisk) ? $rawRisk : "LOW";

        $rawFactor = $result["historicalFactor"] ?? null;
        $factor = is_float($rawFactor) || is_numeric($rawFactor) ? (float) $rawFactor : 1.0;

        /** @var list<string> $reasons */
        $reasons = is_array($result["reasons"] ?? null) ? $result["reasons"] : ["No prior audit anomalies detected"];

        return [
            "historical_risk" => $riskLevel,
            "historical_factor" => $factor,
            "reasoning" => $reasons,
        ];
    }
}
