<?php

declare(strict_types=1);

namespace NAP\Application\Agents;

use NAP\Domain\Events\ClaimAssessmentIngested;
use NAP\Domain\Events\DomainEventInterface;
use NAP\Infrastructure\Integrations\LLM\LLMProviderInterface;
use NAP\Infrastructure\Integrations\LLM\MockLlmProvider;
use NAP\Infrastructure\Messaging\EventListenerInterface;

final class PricingIntelligenceAgent implements EventListenerInterface
{
    private LLMProviderInterface $llmProvider;
    /** @var array<int, array<string, mixed>> */
    private array $evaluations = [];

    public function __construct(?LLMProviderInterface $llmProvider = null)
    {
        $this->llmProvider = $llmProvider ?? new MockLlmProvider();
    }

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof ClaimAssessmentIngested;
    }

    public function handle(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $prompt = "Evaluate parts pricing for claim repair total: " . json_encode($payload);
        
        $rawLlmResponse = $this->llmProvider->generateText($prompt);
        /** @var array<string, mixed> $responseDecoded */
        $responseDecoded = json_decode($rawLlmResponse, true) ?: ["decision" => "REVIEW", "confidence" => 0.5];

        $this->evaluations[] = [
            "agent" => "PricingIntelligenceAgent",
            "provider" => $this->llmProvider->getProviderName(),
            "aggregateId" => $event->getAggregateId(),
            "decision" => is_string($responseDecoded["decision"] ?? null) ? $responseDecoded["decision"] : "REVIEW",
            "confidence" => is_numeric($responseDecoded["confidence"] ?? null) ? (float) $responseDecoded["confidence"] : 0.5,
            "timestamp" => date("c")
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvaluations(): array
    {
        return $this->evaluations;
    }
}
