<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Agents\Pricing;

use NAP\Application\Contracts\ExchangeRateProviderInterface;
use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\DTO\PricingRecommendation;
use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Application\Services\CurrencyConverter;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;

final readonly class PricingIntelligenceAgent
{
    public function __construct(
        private LlmProviderInterface $llmProvider,
        private CurrencyConverter $currencyConverter
    ) {}

    public function analyzePricing(
        string $partNumber,
        NAPMoney $currentPrice,
        string $targetCurrency = "ZAR"
    ): PricingRecommendation {
        $normalizedPrice = $this->currencyConverter->convert($currentPrice, $targetCurrency);

        $context = new PromptContext(
            templateName: "pricing_anomaly_v1",
            variables: [
                "partNumber" => $partNumber,
                "originalAmount" => $currentPrice->getAmountInCents(),
                "originalCurrency" => $currentPrice->getCurrency(),
                "normalizedAmount" => $normalizedPrice->getAmountInCents(),
                "targetCurrency" => $normalizedPrice->getCurrency(),
            ]
        );

        $result = $this->llmProvider->generateStructuredOutput($context);

        $rawAmount = $result["recommendedAmount"] ?? null;
        $recommendedAmount = is_int($rawAmount) || is_numeric($rawAmount)
            ? (int) $rawAmount
            : $normalizedPrice->getAmountInCents();

        $rawConfidence = $result["confidence"] ?? null;
        $confidence = is_float($rawConfidence) || is_numeric($rawConfidence)
            ? (float) $rawConfidence
            : 0.0;

        /** @var list<string> $reasons */
        $reasons = is_array($result["reasons"] ?? null) ? $result["reasons"] : [];

        return new PricingRecommendation(
            partNumber: $partNumber,
            recommendedPrice: NAPMoney::fromCents($recommendedAmount, $normalizedPrice->getCurrency()),
            confidenceScore: $confidence,
            reasoningFactors: $reasons
        );
    }
}
