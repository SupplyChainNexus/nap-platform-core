<?php

declare(strict_types=1);

namespace NAP\Infrastructure\OpenApi;

final class OpenApiGenerator
{
    /**
     * Generates a complete OpenAPI 3.0.3 schema array for the NAP Platform Core API.
     *
     * @return array<string, mixed>
     */
    public function generateSpec(): array
    {
        return [
            "openapi" => "3.0.3",
            "info" => [
                "title" => "NAP Platform Core API",
                "description" => "Core API for Audatex claim ingestion, parts normalization, quote benchmarking, and procurement analytics.",
                "version" => "1.0.0",
                "contact" => [
                    "name" => "Supply Chain Nexus Engineering",
                    "email" => "engineering@supplychainnexus.com"
                ]
            ],
            "servers" => [
                [
                    "url" => "http://localhost:8080",
                    "description" => "Local Development Server"
                ]
            ],
            "paths" => [
                "/health/live" => [
                    "get" => [
                        "summary" => "Liveness Probe",
                        "description" => "Returns HTTP 200 OK if the PHP runtime is responsive.",
                        "responses" => [
                            "200" => [
                                "description" => "System is live",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "status" => ["type" => "string", "example" => "success"],
                                                "data" => [
                                                    "type" => "object",
                                                    "properties" => [
                                                        "status" => ["type" => "string", "example" => "UP"],
                                                        "service" => ["type" => "string", "example" => "nap-platform-core"]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                "/health/ready" => [
                    "get" => [
                        "summary" => "Readiness Probe",
                        "description" => "Checks database connectivity and event store availability.",
                        "responses" => [
                            "200" => ["description" => "System is ready for traffic"],
                            "503" => ["description" => "System unready / Database unreachable"]
                        ]
                    ]
                ],
                "/api/v1/claims/ingest" => [
                    "post" => [
                        "summary" => "Ingest Audatex Claim Webhook",
                        "description" => "Processes incoming Audatex estimate webhooks with optional HMAC signature and idempotency key headers.",
                        "parameters" => [
                            [
                                "name" => "X-Signature-256",
                                "in" => "header",
                                "required" => false,
                                "schema" => ["type" => "string"],
                                "description" => "HMAC-SHA256 signature for payload verification"
                            ],
                            [
                                "name" => "X-Idempotency-Key",
                                "in" => "header",
                                "required" => false,
                                "schema" => ["type" => "string"],
                                "description" => "Unique key to prevent duplicate webhook processing"
                            ]
                        ],
                        "requestBody" => [
                            "required" => true,
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "type" => "object",
                                        "properties" => [
                                            "caseId" => ["type" => "string", "example" => "NXC-80411"],
                                            "claimNumber" => ["type" => "string", "example" => "CLM-2026-9901"],
                                            "document" => ["type" => "object"]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "responses" => [
                            "201" => ["description" => "Claim successfully ingested"],
                            "200" => ["description" => "Idempotent replay detected"],
                            "401" => ["description" => "Invalid HMAC signature"],
                            "400" => ["description" => "Malformed payload"]
                        ]
                    ]
                ],
                "/api/v1/dashboard/summary" => [
                    "get" => [
                        "summary" => "Get Executive Summary Analytics",
                        "description" => "Returns cached executive summary KPI metrics.",
                        "responses" => [
                            "200" => [
                                "description" => "Executive metrics overview",
                                "content" => [
                                    "application/json" => [
                                        "schema" => [
                                            "type" => "object",
                                            "properties" => [
                                                "totalSavingsAmount" => ["type" => "number", "example" => 12500.50],
                                                "benchmarkedQuotesCount" => ["type" => "integer", "example" => 42],
                                                "currency" => ["type" => "string", "example" => "ZAR"]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    /**
     * Export spec as a JSON string.
     */
    public function toJson(bool $prettyPrint = true): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return (string) json_encode($this->generateSpec(), $flags);
    }
}