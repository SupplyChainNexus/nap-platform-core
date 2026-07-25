<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Controllers;

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Infrastructure\Security\HmacSignatureVerifier;
use NAP\Infrastructure\Security\IdempotencyGuard;

final class ClaimIngestController
{
    private IngestAudatexClaimHandler $handler;
    private ?HmacSignatureVerifier $signatureVerifier;
    private ?IdempotencyGuard $idempotencyGuard;

    public function __construct(
        IngestAudatexClaimHandler $handler,
        ?HmacSignatureVerifier $signatureVerifier = null,
        ?IdempotencyGuard $idempotencyGuard = null
    ) {
        $this->handler = $handler;
        $this->signatureVerifier = $signatureVerifier;
        $this->idempotencyGuard = $idempotencyGuard;
    }

    /**
     * Handles incoming Audatex JSON webhooks.
     *
     * @param array<string, mixed> $payload
     * @param string $rawBody
     * @param string|null $signatureHeader
     * @param string|null $idempotencyKeyHeader
     * @return string JSON response
     */
    public function handleWebhook(
        array $payload,
        string $rawBody = "",
        ?string $signatureHeader = null,
        ?string $idempotencyKeyHeader = null
    ): string {
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

        $idKey = $idempotencyKeyHeader ?? (is_string($payload["eventId"] ?? null) ? $payload["eventId"] : null);

        if ($this->idempotencyGuard !== null && $idKey !== null) {
            if ($this->idempotencyGuard->isProcessed($idKey)) {
                http_response_code(200);
                return (string) json_encode([
                    "status" => "success",
                    "code" => 200,
                    "message" => "Webhook already processed (Idempotent replay)",
                    "idempotencyKey" => $idKey
                ]);
            }
        }

        try {
            $caseId = is_string($payload["caseId"] ?? null) ? $payload["caseId"] : "NXC-" . uniqid();
            /** @var array<string, mixed>|string $document */
            $document = $payload["document"] ?? $payload;

            $case = $this->handler->handle($caseId, $document);

            if ($this->idempotencyGuard !== null && $idKey !== null) {
                $this->idempotencyGuard->markProcessed($idKey);
            }

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