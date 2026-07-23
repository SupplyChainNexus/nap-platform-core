<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Logging\StructuredFileLogger;
use PHPUnit\Framework\TestCase;

final class StructuredFileLoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = __DIR__ . "/../../var/logs/test_telemetry.log";
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testLogsStructuredJsonEntry(): void
    {
        $logger = new StructuredFileLogger($this->logFile);
        $logger->info("Agent evaluation completed", [
            "agent" => "AgentOrchestrator",
            "confidence" => 0.93,
        ]);

        $this->assertFileExists($this->logFile);
        $content = (string) file_get_contents($this->logFile);
        
        /** @var array{level: string, message: string, context: array{agent: string, confidence: float}} $decoded */
        $decoded = (array) json_decode(trim($content), true);

        $this->assertSame("INFO", $decoded["level"]);
        $this->assertSame("Agent evaluation completed", $decoded["message"]);
        $this->assertSame("AgentOrchestrator", $decoded["context"]["agent"]);
        $this->assertSame(0.93, $decoded["context"]["confidence"]);
    }
}
