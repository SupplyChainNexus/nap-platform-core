<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;
use InvalidArgumentException;

final readonly class AnalyzePricingApiController
{
    public function __construct(
        private PricingIntelligenceAgent $agent
    ) {}

    /**
     * @param array<string, mixed> $payload
     * @return array{status_code: int, body: array<string, mixed>}
     */
    public function handle(array $payload): array
    {
        $partNumber = $payload["partNumber"] ?? null;
        $amountInCents = $payload["amountInCents"] ?? null;
        $currency = $payload["currency"] ?? "USD";

        if (!is_string($partNumber) || trim($partNumber) === "") {
            return [
                "status_code" => 400,
                "body" => ["status" => "error", "error" => "Missing or invalid partNumber parameter."],
            ];
        }

        if (!is_int($amountInCents) || $amountInCents < 0) {
            return [
                "status_code" => 400,
                "body" => ["status" => "error", "error" => "Missing or invalid amountInCents parameter."],
            ];
        }

        if (!is_string($currency) || trim($currency) === "") {
            $currency = "USD";
        }

        try {
            $money = NAPMoney::fromCents($amountInCents, $currency);
            $recommendation = $this->agent->analyzePricing($partNumber, $money, "ZAR");

            return [
                "status_code" => 200,
                "body" => [
                    "status" => "success",
                    "data" => [
                        "part_number" => $recommendation->partNumber,
                        "recommended_price_cents" => $recommendation->recommendedPrice->getAmountInCents(),
                        "currency" => $recommendation->recommendedPrice->getCurrency(),
                        "confidence" => $recommendation->confidenceScore,
                        "reasoning" => $recommendation->reasoningFactors,
                    ],
                ],
            ];
        } catch (InvalidArgumentException $e) {
            return [
                "status_code" => 422,
                "body" => ["status" => "error", "error" => $e->getMessage()],
            ];
        }
    }
}
