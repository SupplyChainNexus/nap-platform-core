<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Intelligence\HttpLlmProviderAdapter;
use PHPUnit\Framework\TestCase;

final class HttpLlmProviderAdapterTest extends TestCase
{
    public function testAdapterReturnsStructuredOutputInMockMode(): void
    {
        $adapter = new HttpLlmProviderAdapter("mock-key");
        $context = new PromptContext("pricing_v1", [
            "partNumber" => "PART-300",
            "normalizedAmount" => 20000,
            "targetCurrency" => "ZAR"
        ]);

        $result = $adapter->generateStructuredOutput($context);

        $this->assertSame(19000, $result["recommendedAmount"]);
        $this->assertSame(0.93, $result["confidence"]);
        $this->assertCount(2, $result["reasons"]);
    }
}
