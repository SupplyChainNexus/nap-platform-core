<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use DateTimeImmutable;
use NAP\Application\Handlers\CreateCaseHandler;
use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Services\CurrencyConverter;
use NAP\Infrastructure\Console\AnalyzePricingConsoleCommand;
use NAP\Infrastructure\Console\CreateCaseConsoleCommand;
use NAP\Infrastructure\Persistence\DatabaseExchangeRateRepository;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;
use PDO;
use PHPUnit\Framework\TestCase;

final class ConsoleCommandsTest extends TestCase
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

    public function testCreateCaseConsoleCommand(): void
    {
        $mockIdGen = $this->createMock(IdGeneratorInterface::class);
        $mockIdGen->expects($this->once())
            ->method("generate")
            ->willReturn("018e38f9-472b-7b33-8a30-89196b0521e1");

        $mockClock = $this->createMock(ClockInterface::class);
        $mockClock->expects($this->once())
            ->method("now")
            ->willReturn(new DateTimeImmutable("2026-07-23T12:00:00Z"));

        $caseRepo = new InMemoryCaseRepository();
        $handler = new CreateCaseHandler($mockIdGen, $mockClock, $caseRepo);
        $command = new CreateCaseConsoleCommand($handler);

        $result = $command->execute("Mining Equipment Procurement");

        $this->assertSame("success", $result["status"]);
        $this->assertSame("018e38f9-472b-7b33-8a30-89196b0521e1", $result["case_id"]);
    }

    public function testAnalyzePricingConsoleCommandWithDbExchangeRate(): void
    {
        $rateRepo = new DatabaseExchangeRateRepository($this->pdo);
        $rateRepo->saveRate("USD", "ZAR", 18.50);
        $converter = new CurrencyConverter($rateRepo);

        $mockLlm = $this->createMock(LlmProviderInterface::class);
        $mockLlm->expects($this->once())
            ->method("generateStructuredOutput")
            ->willReturn([
                "recommendedAmount" => 170000,
                "confidence" => 0.95,
                "reasons" => ["Bulk order rebate applicable"],
            ]);

        $agent = new PricingIntelligenceAgent($mockLlm, $converter);
        $command = new AnalyzePricingConsoleCommand($agent);

        // $100.00 USD input (10000 cents)
        $result = $command->execute("PART-500", 10000, "USD");

        $this->assertSame("PART-500", $result["part_number"]);
        $this->assertSame(170000, $result["recommended_price_cents"]);
        $this->assertSame("ZAR", $result["currency"]);
        $this->assertSame(0.95, $result["confidence"]);
    }
}
