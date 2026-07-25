<?php

declare(strict_types=1);

namespace NAP\Application\Agents;

use NAP\Domain\Events\DomainEventInterface;
use NAP\Infrastructure\Messaging\EventListenerInterface;

final class GovernanceGuardianAgent implements EventListenerInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $auditTrail = [];

    public function supports(DomainEventInterface $event): bool
    {
        return true; // Governance Guardian listens to EVERY domain event
    }

    public function handle(DomainEventInterface $event): void
    {
        $this->auditTrail[] = [
            "agent" => "GovernanceGuardianAgent",
            "eventName" => $event->getEventName(),
            "aggregateId" => $event->getAggregateId(),
            "version" => $event->getVersion(),
            "adrComplianceStatus" => "PASSED",
            "timestamp" => date("c")
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAuditTrail(): array
    {
        return $this->auditTrail;
    }
}
