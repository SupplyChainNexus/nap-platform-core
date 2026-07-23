<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OpenApiSpecTest extends TestCase
{
    public function testOpenApiSpecIsValidAndContainsRequiredEndpoints(): void
    {
        $filePath = __DIR__ . "/../../resources/docs/openapi.json";
        $this->assertFileExists($filePath);

        /** @var string $content */
        $content = file_get_contents($filePath);
        $spec = json_decode($content, true);

        $this->assertIsArray($spec);
        $this->assertSame("3.0.3", $spec["openapi"] ?? null);
        $this->assertArrayHasKey("/api/cases", $spec["paths"] ?? []);
        $this->assertArrayHasKey("/api/pricing/analyze", $spec["paths"] ?? []);
    }
}
