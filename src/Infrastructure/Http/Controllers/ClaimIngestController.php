<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Infrastructure\Http\JsonResponse;

final class ClaimIngestController
{
    private IngestAudatexClaimHandler $handler;

    public function __construct(?IngestAudatexClaimHandler $handler = null)
    {
        $this->handler = $handler ?? new IngestAudatexClaimHandler();
    }

    /**
     * @param array<string, mixed> $requestPayload
     * @return string
     */
    public function handleWebhook(array $requestPayload): string
    {
        $caseId = is_string($requestPayload["caseId"] ?? null) ? $requestPayload["caseId"] : "NXC-" . uniqid();
        
        /** @var array<string, mixed>|string $documentData */
        $documentData = $requestPayload["document"] ?? $requestPayload;

        $case = $this->handler->handle($caseId, $documentData);

        return JsonResponse::create([
            "caseId" => $case->getCaseId(),
            "claimNumber" => $case->getClaimNumber(),
            "status" => $case->getStatus(),
            "version" => $case->getVersion()
        ], 201);
    }
}
