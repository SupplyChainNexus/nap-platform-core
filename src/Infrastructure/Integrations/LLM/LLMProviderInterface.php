<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Integrations\LLM;

interface LLMProviderInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function generateText(string $prompt, array $options = []): string;

    public function getProviderName(): string;
}
