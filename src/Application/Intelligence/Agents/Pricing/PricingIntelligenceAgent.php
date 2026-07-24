<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Agents\Pricing;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\DTO\PricingRecommendation;
use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Application\Services\CurrencyConverter;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;

final class PricingIntelligenceAgent
{
    private LlmProviderInterface $provider;
    private ?CurrencyConverter $currencyConverter;

    public function __construct(LlmProviderInterface $provider, ?CurrencyConverter $currencyConverter = null)
    {
        $this->provider = $provider;
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * @param string $partNumber
     * @param NAPMoney $baseAmount
     * @param string $targetCurrency
     * @return PricingRecommendation
     */
    public function analyzePricing(string $partNumber, NAPMoney $baseAmount, string $targetCurrency = "ZAR"): PricingRecommendation
    {
        $workingAmount = $baseAmount;

        // Convert baseAmount if its currency differs from targetCurrency and converter is present
        if ($this->currencyConverter !== null && $baseAmount->getCurrency() !== $targetCurrency) {
            $workingAmount = $this->currencyConverter->convert($baseAmount, $targetCurrency);
        }

        $cents = $workingAmount->getAmountInCents();

        $promptContext = new PromptContext("pricing_evaluation_v1", [
            "partNumber" => $partNumber,
            "normalizedAmount" => $cents,
            "currency" => $targetCurrency
        ]);

        $output = $this->provider->generateStructuredOutput($promptContext, []);

        $recAmount = is_numeric($output["recommendedAmount"] ?? null) 
            ? (int) round((float) $output["recommendedAmount"]) 
            : (int) round($cents * 0.90);
            
        $confidence = is_numeric($output["confidence"] ?? null) ? (float) $output["confidence"] : 0.88;
        
        /** @var array<int, string> $reasons */
        $reasons = is_array($output["reasons"] ?? null) ? $output["reasons"] : ["Evaluated via AI Pricing Intelligence Agent"];

        $recommendedMoney = NAPMoney::fromCents($recAmount, $targetCurrency);

        return new PricingRecommendation(
            partNumber: $partNumber,
            recommendedPrice: $recommendedMoney,
            confidenceScore: $confidence,
            reasoningFactors: $reasons
        );
    }

    /**
     * @param array<string, mixed> $context
     * @return array{recommendedAmount: float, confidence: float, reasons: array<int, string>}
     */
    public function evaluate(array $context): array
    {
        $partNumber = is_string($context["partNumber"] ?? null) ? (string) $context["partNumber"] : "NAP-UNKNOWN";
        $rawAmount = $context["normalizedAmount"] ?? $context["amount"] ?? 10000;
        $cents = is_numeric($rawAmount) ? (int) round((float) $rawAmount) : 10000;
        $currency = is_string($context["currency"] ?? null) ? (string) $context["currency"] : "ZAR";

        $money = NAPMoney::fromCents($cents, $currency);
        $rec = $this->analyzePricing($partNumber, $money, "ZAR");

        return [
            "recommendedAmount" => (float) $rec->recommendedPrice->getAmountInCents(),
            "confidence" => $rec->confidenceScore,
            "reasons" => $rec->reasoningFactors
        ];
    }
}

