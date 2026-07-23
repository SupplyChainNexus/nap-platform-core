<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Console;

use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;

final readonly class AnalyzePricingConsoleCommand
{
    public function __construct(
        private PricingIntelligenceAgent $agent
    ) {}

    /**
     * @return array{part_number: string, recommended_price_cents: int, currency: string, confidence: float, reasoning: list<string>}
     */
    public function execute(string $partNumber, int $amountInCents, string $currency = "USD"): array
    {
        $money = NAPMoney::fromCents($amountInCents, $currency);
        $recommendation = $this->agent->analyzePricing($partNumber, $money, "ZAR");

        return [
            "part_number" => $recommendation->partNumber,
            "recommended_price_cents" => $recommendation->recommendedPrice->getAmountInCents(),
            "currency" => $recommendation->recommendedPrice->getCurrency(),
            "confidence" => $recommendation->confidenceScore,
            "reasoning" => $recommendation->reasoningFactors,
        ];
    }
}
