<?php

declare(strict_types=1);

namespace NAP\Application\Agents;

use NAP\Domain\Events\DomainEventInterface;
use NAP\Domain\Events\VehicleIdentified;
use NAP\Infrastructure\Messaging\EventListenerInterface;

final class VehicleIntelligenceAgent implements EventListenerInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $verifiedVehicles = [];

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof VehicleIdentified;
    }

    public function handle(DomainEventInterface $event): void
    {
        $payload = $event->getPayload();
        $this->verifiedVehicles[] = [
            "agent" => "VehicleIntelligenceAgent",
            "vin" => is_string($payload["vin"] ?? null) ? $payload["vin"] : "UNKNOWN",
            "make" => is_string($payload["make"] ?? null) ? $payload["make"] : "UNKNOWN",
            "model" => is_string($payload["model"] ?? null) ? $payload["model"] : "UNKNOWN",
            "verified" => true
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getVerifiedVehicles(): array
    {
        return $this->verifiedVehicles;
    }
}
