<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Contracts\ExchangeRateProviderInterface;
use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Services\CurrencyConverter;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;
use PHPUnit\Framework\TestCase;

final class PricingIntelligenceAgentTest extends TestCase
{
    public function testAgentReturnsValidPricingRecommendationInZar(): void
    {
        $mockLlm = $this->createMock(LlmProviderInterface::class);
        $mockLlm->expects($this->once())
            ->method("generateStructuredOutput")
            ->willReturn([
                "recommendedAmount" => 14500,
                "confidence" => 0.92,
                "reasons" => ["Historical bulk discount detected", "Market average is lower"],
            ]);

        $mockRateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $mockRateProvider->expects($this->never())->method("getRate");
        $converter = new CurrencyConverter($mockRateProvider);

        $agent = new PricingIntelligenceAgent($mockLlm, $converter);
        $recommendation = $agent->analyzePricing("PART-100", NAPMoney::fromCents(16000, "ZAR"));

        $this->assertSame("PART-100", $recommendation->partNumber);
        $this->assertSame(14500, $recommendation->recommendedPrice->getAmountInCents());
        $this->assertSame("ZAR", $recommendation->recommendedPrice->getCurrency());
        $this->assertSame(0.92, $recommendation->confidenceScore);
        $this->assertCount(2, $recommendation->reasoningFactors);
    }

    public function testAgentConvertsUsdQuoteToZarBeforeAnalysis(): void
    {
        $mockLlm = $this->createMock(LlmProviderInterface::class);
        $mockLlm->expects($this->once())
            ->method("generateStructuredOutput")
            ->willReturn([
                "recommendedAmount" => 175000,
                "confidence" => 0.88,
                "reasons" => ["Exchange rate fluctuation adjusted"],
            ]);

        $mockRateProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $mockRateProvider->expects($this->once())
            ->method("getRate")
            ->with("USD", "ZAR")
            ->willReturn(18.50);
        $converter = new CurrencyConverter($mockRateProvider);

        $agent = new PricingIntelligenceAgent($mockLlm, $converter);
        // $100.00 USD quote
        $usdQuote = NAPMoney::fromCents(10000, "USD");
        $recommendation = $agent->analyzePricing("PART-USD-100", $usdQuote, "ZAR");

        $this->assertSame("PART-USD-100", $recommendation->partNumber);
        $this->assertSame(175000, $recommendation->recommendedPrice->getAmountInCents());
        $this->assertSame("ZAR", $recommendation->recommendedPrice->getCurrency());
        $this->assertSame(0.88, $recommendation->confidenceScore);
    }
}
