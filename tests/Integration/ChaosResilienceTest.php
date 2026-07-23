<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Http\HealthCheckController;
use NAP\Infrastructure\Http\RateLimitMiddleware;
use NAP\Infrastructure\Intelligence\ResilientLlmProviderAdapter;
use NAP\Infrastructure\Persistence\OutboxPublisher;
use NAP\SharedKernel\EnvironmentLoader;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChaosResilienceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE outbox_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                payload TEXT NOT NULL,
                created_at DATETIME NOT NULL,
                published_at DATETIME NULL
            );
            CREATE TABLE rate_limits (
                key TEXT PRIMARY KEY,
                tokens REAL NOT NULL,
                last_updated INTEGER NOT NULL
            );
        ");
    }

    public function testEnvironmentLoaderAndHealthCheckController(): void
    {
        EnvironmentLoader::load(__DIR__ . "/../../.env.example");
        $this->assertSame("development", getenv("APP_ENV"));

        $health = new HealthCheckController($this->pdo);
        $status = $health->check();

        $this->assertSame("healthy", $status["status"]);
        $this->assertSame("ok", $status["checks"]["database"]);
    }

    public function testChaosRateLimitingAndAdapterResilience(): void
    {
        // Rate limiting pressure
        $limiter = new RateLimitMiddleware($this->pdo, 2, 60);
        $this->assertTrue($limiter->allowRequest("10.0.0.1"));
        $this->assertTrue($limiter->allowRequest("10.0.0.1"));
        $this->assertFalse($limiter->allowRequest("10.0.0.1"));

        // Resilient adapter transient recovery under pressure
        $mockLlm = $this->createMock(LlmProviderInterface::class);
        $mockLlm->expects($this->exactly(2))
            ->method("generateStructuredOutput")
            ->will($this->onConsecutiveCalls(
                $this->throwException(new RuntimeException("Transient 503 Server Error")),
                ["status" => "RECOVERED"]
            ));

        $resilient = new ResilientLlmProviderAdapter($mockLlm, 3, 2);
        $result = $resilient->generateStructuredOutput(new PromptContext("test", []));
        $this->assertSame("RECOVERED", $result["status"]);
    }
}
