<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\DTO;

use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;

final readonly class PricingRecommendation
{
    /**
     * @param list<string> $reasoningFactors
     */
    public function __construct(
        public string $partNumber,
        public NAPMoney $recommendedPrice,
        public float $confidenceScore,
        public array $reasoningFactors
    ) {}
}
