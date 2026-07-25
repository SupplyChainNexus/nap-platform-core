<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Infrastructure\Health\HealthCheckRegistry;
use NAP\Infrastructure\Http\JsonResponse;

final class HealthController
{
    private HealthCheckRegistry $healthRegistry;

    public function __construct(HealthCheckRegistry $healthRegistry)
    {
        $this->healthRegistry = $healthRegistry;
    }

    /**
     * Returns HTTP 200 OK for Liveness Probes.
     */
    public function getLiveness(): string
    {
        return JsonResponse::create([
            "status" => "UP",
            "service" => "nap-platform-core",
            "timestamp" => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM)
        ], 200);
    }

    /**
     * Returns HTTP 200 OK if system is ready, or HTTP 503 Service Unavailable if database is unreachable.
     */
    public function getReadiness(): string
    {
        $diagnostics = $this->healthRegistry->checkReadiness();
        $statusCode = $diagnostics["status"] === "UP" ? 200 : 503;

        return JsonResponse::create($diagnostics, $statusCode);
    }
}