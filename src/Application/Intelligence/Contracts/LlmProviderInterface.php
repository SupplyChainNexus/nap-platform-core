<?php

declare(strict_types=1);

namespace NAP\Application\Intelligence\Contracts;

use NAP\Application\Intelligence\Prompting\PromptContext;

interface LlmProviderInterface
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function generateStructuredOutput(PromptContext $context, array $options = []): array;
}
