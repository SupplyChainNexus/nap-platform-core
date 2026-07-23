<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use DateTimeImmutable;
use NAP\Application\Handlers\CreateCaseHandler;
use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Services\CurrencyConverter;
use NAP\Infrastructure\Http\AnalyzePricingApiController;
use NAP\Infrastructure\Http\CreateCaseApiController;
use NAP\Infrastructure\Persistence\DatabaseExchangeRateRepository;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;
use PDO;
use PHPUnit\Framework\TestCase;

final class HttpControllersTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /** @var string $schema2 */
        $schema2 = file_get_contents(__DIR__ . "/../../resources/migrations/002_exchange_rates.sql");
        $this->pdo->exec($schema2);
    }

    public function testCreateCaseHttpControllerReturns201OnSuccess(): void
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

        $response = $controller->handle(["businessContext" => "Logistics Expansion"]);

        $this->assertSame(201, $response["status_code"]);
        $this->assertSame("success", $response["body"]["status"]);
        $this->assertSame("018e38f9-472b-7b33-8a30-89196b0521e1", $response["body"]["case_id"]);
    }

    public function testCreateCaseHttpControllerReturns400OnInvalidPayload(): void
    {
        $mockIdGen = $this->createMock(IdGeneratorInterface::class);
        $mockClock = $this->createMock(ClockInterface::class);

        $handler = new CreateCaseHandler($mockIdGen, $mockClock, new InMemoryCaseRepository());
        $controller = new CreateCaseApiController($handler);

        $response = $controller->handle([]);

        $this->assertSame(400, $response["status_code"]);
        $this->assertSame("error", $response["body"]["status"]);
    }

    public function testAnalyzePricingHttpControllerReturns200WithConvertedCurrency(): void
    {
        $rateRepo = new DatabaseExchangeRateRepository($this->pdo);
        $rateRepo->saveRate("EUR", "ZAR", 20.00);
        $converter = new CurrencyConverter($rateRepo);

        $mockLlm = $this->createMock(LlmProviderInterface::class);
        $mockLlm->expects($this->once())
            ->method("generateStructuredOutput")
            ->willReturn([
                "recommendedAmount" => 190000,
                "confidence" => 0.91,
                "reasons" => ["Volume discount applied"],
            ]);

        $agent = new PricingIntelligenceAgent($mockLlm, $converter);
        $controller = new AnalyzePricingApiController($agent);

        // 100 EUR = 10000 cents
        $response = $controller->handle([
            "partNumber" => "PART-EUR-900",
            "amountInCents" => 10000,
            "currency" => "EUR",
        ]);

        $this->assertSame(200, $response["status_code"]);
        $this->assertSame("success", $response["body"]["status"]);
        $data = $response["body"]["data"];
        $this->assertSame("PART-EUR-900", $data["part_number"]);
        $this->assertSame(190000, $data["recommended_price_cents"]);
        $this->assertSame("ZAR", $data["currency"]);
    }
}
