<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Integrations\LLM;

final class MockLlmProvider implements LLMProviderInterface
{
    private string $presetResponse;

    public function __construct(string $presetResponse = '{"decision": "APPROVED", "confidence": 0.95}')
    {
        $this->presetResponse = $presetResponse;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generateText(string $prompt, array $options = []): string
    {
        return $this->presetResponse;
    }

    public function getProviderName(): string
    {
        return "Mock LLM Provider";
    }
}
