<?php

declare(strict_types=1);

namespace NAP\Domain\Intelligence;

use NAP\Application\Intelligence\Prompting\PromptContext;

interface LlmProviderInterface
{
    /**
     * @return array{recommendedAmount: int|float, confidence: float, reasons: array<int, string>}
     */
    public function generateStructuredOutput(PromptContext $context): array;
}

