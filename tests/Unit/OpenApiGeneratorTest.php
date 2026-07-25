<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\OpenApi\OpenApiGenerator;
use PHPUnit\Framework\TestCase;

final class OpenApiGeneratorTest extends TestCase
{
    public function testGenerateSpecContainsCoreEndpoints(): void
    {
        $generator = new OpenApiGenerator();
        $spec = $generator->generateSpec();

        $this->assertEquals("3.0.3", $spec["openapi"]);
        $this->assertEquals("NAP Platform Core API", $spec["info"]["title"]);
        $this->assertArrayHasKey("/health/live", $spec["paths"]);
        $this->assertArrayHasKey("/health/ready", $spec["paths"]);
        $this->assertArrayHasKey("/api/v1/claims/ingest", $spec["paths"]);
        $this->assertArrayHasKey("/api/v1/dashboard/summary", $spec["paths"]);
    }

    public function testToJsonOutputsValidFormattedJson(): void
    {
        $generator = new OpenApiGenerator();
        $json = $generator->toJson();

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertEquals("3.0.3", $decoded["openapi"]);
    }
}