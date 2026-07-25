<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Application\Services\PartLookupService;
use NAP\Infrastructure\Http\JsonResponse;

final class PartCrossReferenceController
{
    private PartLookupService $lookupService;

    public function __construct(PartLookupService $lookupService)
    {
        $this->lookupService = $lookupService;
    }

    /**
     * Handles HTTP GET /api/v1/parts/cross-reference?oem={oemPartNumber}
     *
     * @param array<string, mixed> $queryParams
     * @return array{status_code: int, body: array<string, mixed>}
     */
    public function handle(array $queryParams): array
    {
        $oemPartNumber = is_string($queryParams["oem"] ?? null) ? trim((string) $queryParams["oem"]) : "";

        if ($oemPartNumber === "") {
            return [
                "status_code" => 400,
                "body" => [
                    "status" => "error",
                    "message" => "Query parameter 'oem' is required.",
                ],
            ];
        }

        $alternatives = $this->lookupService->getAlternativeParts($oemPartNumber);

        return [
            "status_code" => 200,
            "body" => [
                "status" => "success",
                "oemPartNumber" => strtoupper($oemPartNumber),
                "matchesCount" => count($alternatives),
                "alternatives" => $alternatives,
            ],
        ];
    }
}
