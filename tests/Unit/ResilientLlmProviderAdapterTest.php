<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Intelligence\ResilientLlmProviderAdapter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ResilientLlmProviderAdapterTest extends TestCase
{
    public function testRetriesOnFailureAndSucceeds(): void
    {
        $mockInner = $this->createMock(LlmProviderInterface::class);
        $mockInner->expects($this->exactly(2))
            ->method("generateStructuredOutput")
            ->will($this->onConsecutiveCalls(
                $this->throwException(new RuntimeException("Transient network error")),
                ["result" => "success"]
            ));

        $adapter = new ResilientLlmProviderAdapter($mockInner, 3, 5);
        $output = $adapter->generateStructuredOutput(new PromptContext("test_prompt", []));

        $this->assertSame("success", $output["result"]);
    }

    public function testThrowsExceptionWhenAllRetriesFail(): void
    {
        $mockInner = $this->createMock(LlmProviderInterface::class);
        $mockInner->expects($this->exactly(3))
            ->method("generateStructuredOutput")
            ->willThrowException(new RuntimeException("Persistent API Outage"));

        $adapter = new ResilientLlmProviderAdapter($mockInner, 3, 5);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Resilient LLM Adapter failed after 3 attempts");

        $adapter->generateStructuredOutput(new PromptContext("test_prompt", []));
    }
}
