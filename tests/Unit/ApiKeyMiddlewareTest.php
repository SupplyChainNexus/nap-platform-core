<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Infrastructure\Http\Middleware\ApiKeyMiddleware;
use PHPUnit\Framework\TestCase;

final class ApiKeyMiddlewareTest extends TestCase
{
    private ApiKeyMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new ApiKeyMiddleware(["secret-nap-key-123"]);
    }

    public function testAllowsRequestWithValidBearerToken(): void
    {
        $headers = ["Authorization" => "Bearer secret-nap-key-123"];
        $result = $this->middleware->process($headers);

        $this->assertNull($result);
    }

    public function testBlocksRequestWithMissingAuthHeader(): void
    {
        $result = $this->middleware->process([]);

        $this->assertNotNull($result);
        $this->assertSame(401, $result["status_code"]);
        $this->assertStringContainsString("Missing or malformed", $result["body"]["error"]);
    }

    public function testBlocksRequestWithInvalidToken(): void
    {
        $headers = ["Authorization" => "Bearer wrong-token"];
        $result = $this->middleware->process($headers);

        $this->assertNotNull($result);
        $this->assertSame(401, $result["status_code"]);
        $this->assertStringContainsString("Invalid API key", $result["body"]["error"]);
    }
}
