<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Http\RateLimitMiddleware;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE rate_limits (
                key TEXT PRIMARY KEY,
                tokens REAL NOT NULL,
                last_updated INTEGER NOT NULL
            );
        ");
    }

    public function testAllowsRequestsWithinLimit(): void
    {
        $limiter = new RateLimitMiddleware($this->pdo, 3, 60);

        $this->assertTrue($limiter->allowRequest("127.0.0.1"));
        $this->assertTrue($limiter->allowRequest("127.0.0.1"));
        $this->assertTrue($limiter->allowRequest("127.0.0.1"));
        $this->assertFalse($limiter->allowRequest("127.0.0.1"));
    }
}
