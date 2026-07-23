<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use NAP\Application\Intelligence\Agents\Pricing\AgentOrchestrator;
use NAP\Application\Intelligence\Agents\Pricing\HistoricalAuditAgent;
use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\Application\Intelligence\Agents\Pricing\SupplierReputationAgent;
use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Services\CurrencyConverter;
use NAP\Infrastructure\Persistence\DatabaseExchangeRateRepository;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;
use PDO;
use PHPUnit\Framework\TestCase;

final class AgentOrchestratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /** @var string $schema */
        $schema = file_get_contents(__DIR__ . "/../../resources/migrations/002_exchange_rates.sql");
        $this->pdo->exec($schema);
    }

    public function testOrchestratorAggregatesMultipleAgentsIntoDecisionMatrix(): void
    {
        $rateRepo = new DatabaseExchangeRateRepository($this->pdo);
        $rateRepo->saveRate("USD", "ZAR", 18.50);
        $converter = new CurrencyConverter($rateRepo);

        $mockLlm = $this->createMock(LlmProviderInterface::class);
        $mockLlm->expects($this->exactly(3))
            ->method("generateStructuredOutput")
            ->willReturnOnConsecutiveCalls(
                // 1. Pricing Intelligence Agent
                [
                    "recommendedAmount" => 185000,
                    "confidence" => 0.90,
                    "reasons" => ["Base price converted safely"],
                ],
                // 2. Historical Audit Agent
                [
                    "riskLevel" => "LOW",
                    "historicalFactor" => 0.98,
                    "reasons" => ["Historical volume rebate applied"],
                ],
                // 3. Supplier Reputation Agent
                [
                    "reputationScore" => 0.95,
                    "riskFlags" => [],
                ]
            );

        $pricingAgent = new PricingIntelligenceAgent($mockLlm, $converter);
        $auditAgent = new HistoricalAuditAgent($mockLlm);
        $reputationAgent = new SupplierReputationAgent($mockLlm);

        $orchestrator = new AgentOrchestrator($pricingAgent, $auditAgent, $reputationAgent);

        // $100.00 USD quote
        $quote = NAPMoney::fromCents(10000, "USD");
        $matrix = $orchestrator->evaluate("PART-888", "SUPPLIER-01", $quote);

        $this->assertSame("PART-888", $matrix["part_number"]);
        $this->assertSame("SUPPLIER-01", $matrix["supplier_id"]);
        $this->assertSame(181300, $matrix["recommended_price_cents"]); // 185000 * 0.98
        $this->assertSame("ZAR", $matrix["currency"]);
        $this->assertSame(0.93, $matrix["overall_confidence"]); // (0.90 + 0.95) / 2
        $this->assertSame(0.95, $matrix["reputation_score"]);
        $this->assertCount(2, $matrix["all_reasons"]);
    }
}
