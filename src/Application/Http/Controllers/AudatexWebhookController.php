<?php

declare(strict_types=1);

namespace NAP\Application\Http\Controllers;

use NAP\Application\Services\AudatexClaimParserService;

final class AudatexWebhookController
{
    private AudatexClaimParserService $parserService;

    public function __construct(AudatexClaimParserService $parserService)
    {
        $this->parserService = $parserService;
    }

    /**
     * Handles Audatex Claim Webhook POST Requests.
     *
     * @param array<string, mixed> $requestData
     * @return array<string, mixed>
     */
    public function handleIngest(array $requestData): array
    {
        $rawText = (string) ($requestData['rawText'] ?? $requestData['content'] ?? '');
        $preferredSupplier = isset($requestData['preferredSupplier']) ? (string) $requestData['preferredSupplier'] : null;

        $result = $this->parserService->parseAndEvaluateClaim($rawText, $preferredSupplier);

        return [
            'status'  => 'success',
            'code'    => 200,
            'message' => 'Audatex Claim parsed and evaluated successfully against preferred supplier price caps.',
            'data'    => $result
        ];
    }
}
