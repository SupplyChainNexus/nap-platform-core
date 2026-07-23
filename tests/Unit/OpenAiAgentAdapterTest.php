<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Agents\OpenAiAgentAdapter;
use PHPUnit\Framework\TestCase;

final class OpenAiAgentAdapterTest extends TestCase
{
    public function testFallbackBehaviorWhenNoApiKeyProvided(): void
    {
        $adapter = new OpenAiAgentAdapter(apiKey: "");
        $context = new PromptContext(
            templateName: "pricing_v1",
            variables: ["normalizedAmount" => 20000]
        );

        $result = $adapter->generateStructuredOutput($context);

        $this->assertSame(19000, $result["recommendedAmount"]);
        $this->assertSame(0.85, $result["confidence"]);
        $this->assertNotEmpty($result["reasons"]);
    }

    public function testHandlesCircuitBreakerFailureWithInvalidKey(): void
    {
        $adapter = new OpenAiAgentAdapter(apiKey: "invalid_key_for_testing");
        $context = new PromptContext(
            templateName: "pricing_v1",
            variables: ["normalizedAmount" => 15000]
        );

        $result = $adapter->generateStructuredOutput($context);

        $this->assertSame(15000, $result["recommendedAmount"]);
        $this->assertSame(0.50, $result["confidence"]);
        $this->assertStringContainsString("[Circuit Breaker Fallback]", $result["reasons"][0] ?? "");
    }
}
