<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use DateTimeImmutable;
use NAP\Application\Handlers\CreateCaseHandler;
use NAP\Infrastructure\Http\CreateCaseApiController;
use NAP\Infrastructure\Http\Router;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testRouterDispatchesToRegisteredController(): void
    {
        $mockIdGen = $this->createMock(IdGeneratorInterface::class);
        $mockIdGen->expects($this->once())
            ->method("generate")
            ->willReturn("018e38f9-472b-7b33-8a30-89196b0521e1");

        $mockClock = $this->createMock(ClockInterface::class);
        $mockClock->expects($this->once())
            ->method("now")
            ->willReturn(new DateTimeImmutable("2026-07-23T12:00:00Z"));

        $handler = new CreateCaseHandler($mockIdGen, $mockClock, new InMemoryCaseRepository());
        $controller = new CreateCaseApiController($handler);

        $router = new Router();
        $router->post("/api/cases", fn(array $payload) => $controller->handle($payload));

        $response = $router->dispatch("POST", "/api/cases", ["businessContext" => "Router Test Context"]);

        $this->assertSame(201, $response["status_code"]);
        $this->assertSame("success", $response["body"]["status"]);
    }

    public function testRouterReturns404ForUnregisteredRoute(): void
    {
        $router = new Router();
        $response = $router->dispatch("GET", "/api/non-existent");

        $this->assertSame(404, $response["status_code"]);
        $this->assertSame("error", $response["body"]["status"]);
    }
}
