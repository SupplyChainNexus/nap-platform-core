<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Infrastructure\Security\HmacSignatureVerifier;

final class ClaimIngestController
{
    private IngestAudatexClaimHandler $handler;
    private ?HmacSignatureVerifier $signatureVerifier;

    public function __construct(
        IngestAudatexClaimHandler $handler,
        ?HmacSignatureVerifier $signatureVerifier = null
    ) {
        $this->handler = $handler;
        $this->signatureVerifier = $signatureVerifier;
    }

    /**
     * Handles incoming Audatex JSON webhooks.
     *
     * @param array<string, mixed> $payload
     * @param string $rawBody
     * @param string|null $signatureHeader
     * @return string JSON response
     */
    public function handleWebhook(array $payload, string $rawBody = "", ?string $signatureHeader = null): string
    {
        if ($this->signatureVerifier !== null) {
            if ($signatureHeader === null || !$this->signatureVerifier->verify($rawBody, $signatureHeader)) {
                http_response_code(401);
                return (string) json_encode([
                    "status" => "error",
                    "code" => 401,
                    "message" => "Invalid or missing HMAC webhook signature"
                ]);
            }
        }

        try {
            $caseId = is_string($payload["caseId"] ?? null) ? $payload["caseId"] : "NXC-" . uniqid();
            /** @var array<string, mixed>|string $document */
            $document = $payload["document"] ?? $payload;

            $case = $this->handler->handle($caseId, $document);

            http_response_code(201);
            return (string) json_encode([
                "status" => "success",
                "code" => 201,
                "message" => "Audatex claim ingested successfully",
                "data" => [
                    "caseId" => $case->getCaseId(),
                    "claimNumber" => $case->getClaimNumber(),
                    "status" => $case->getStatus(),
                    "version" => $case->getVersion()
                ]
            ]);
        } catch (\Throwable $e) {
            http_response_code(400);
            return (string) json_encode([
                "status" => "error",
                "code" => 400,
                "message" => $e->getMessage()
            ]);
        }
    }
}