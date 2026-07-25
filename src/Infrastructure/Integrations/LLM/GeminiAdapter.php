<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Integrations\LLM;

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Agents\GeminiAgentAdapter;

final class GeminiAdapter implements LLMProviderInterface
{
    private GeminiAgentAdapter $agentAdapter;

    public function __construct(?GeminiAgentAdapter $agentAdapter = null)
    {
        $this->agentAdapter = $agentAdapter ?? new GeminiAgentAdapter();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function generateText(string $prompt, array $options = []): string
    {
        $context = new PromptContext("default", ["partNumber" => "NAP-ITEM", "normalizedAmount" => 10000]);
        $result = $this->agentAdapter->generateStructuredOutput($context, $options);
        return (string) json_encode($result);
    }

    public function getProviderName(): string
    {
        return "Google Gemini Agent Adapter";
    }
}
