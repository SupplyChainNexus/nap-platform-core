<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Intelligence;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;
use RuntimeException;

final readonly class ResilientLlmProviderAdapter implements LlmProviderInterface
{
    public function __construct(
        private LlmProviderInterface $primaryAdapter,
        private int $maxRetries = 3,
        private int $initialDelayMs = 50
    ) {}

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function generateStructuredOutput(PromptContext $context, array $options = []): array
    {
        $attempts = 0;
        $delay = $this->initialDelayMs;

        while ($attempts < $this->maxRetries) {
            try {
                return $this->primaryAdapter->generateStructuredOutput($context, $options);
            } catch (\Throwable $e) {
                $attempts++;
                if ($attempts >= $this->maxRetries) {
                    throw new RuntimeException(sprintf("Resilient LLM Adapter failed after %d attempts: %s", $this->maxRetries, $e->getMessage()), 0, $e);
                }
                usleep($delay * 1000);
                $delay *= 2; // Exponential backoff
            }
        }

        throw new RuntimeException("Resilient LLM Adapter unexpected retry escape.");
    }
}
