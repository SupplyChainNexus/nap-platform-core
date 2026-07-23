<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Agents\Pricing;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;

final readonly class SupplierReputationAgent
{
    public function __construct(
        private LlmProviderInterface $llmProvider
    ) {}

    /**
     * @return array{reputation_score: float, risk_flags: list<string>}
     */
    public function evaluateSupplier(string $supplierId): array
    {
        $context = new PromptContext("supplier_reputation_v1", ["supplierId" => $supplierId]);
        $result = $this->llmProvider->generateStructuredOutput($context);

        $rawScore = $result["reputationScore"] ?? null;
        $score = is_float($rawScore) || is_numeric($rawScore) ? (float) $rawScore : 0.85;

        /** @var list<string> $riskFlags */
        $riskFlags = is_array($result["riskFlags"] ?? null) ? $result["riskFlags"] : [];

        return [
            "reputation_score" => $score,
            "risk_flags" => $riskFlags,
        ];
    }
}
