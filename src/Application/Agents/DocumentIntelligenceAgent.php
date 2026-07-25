<?php

declare(strict_types=1);

namespace NAP\Application\Agents;

use NAP\Domain\Events\AuthorizationReceived;
use NAP\Domain\Events\DomainEventInterface;
use NAP\Infrastructure\Messaging\EventListenerInterface;

final class DocumentIntelligenceAgent implements EventListenerInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $processedLogs = [];

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof AuthorizationReceived;
    }

    public function handle(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $this->processedLogs[] = [
            "agent" => "DocumentIntelligenceAgent",
            "status" => "ANALYZED",
            "claimNumber" => is_string($payload["claimNumber"] ?? null) ? $payload["claimNumber"] : "UNKNOWN",
            "insurer" => is_string($payload["insurer"] ?? null) ? $payload["insurer"] : "UNKNOWN",
            "timestamp" => date("c")
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProcessedLogs(): array
    {
        return $this->processedLogs;
    }
}
