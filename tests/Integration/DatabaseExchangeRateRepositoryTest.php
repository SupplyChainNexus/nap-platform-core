<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use InvalidArgumentException;
use NAP\Infrastructure\Persistence\DatabaseExchangeRateRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseExchangeRateRepositoryTest extends TestCase
{
    private PDO $pdo;
    private DatabaseExchangeRateRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO("sqlite::memory:");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /** @var string $schema */
        $schema = file_get_contents(__DIR__ . "/../../resources/migrations/002_exchange_rates.sql");
        $this->pdo->exec($schema);

        $this->repository = new DatabaseExchangeRateRepository($this->pdo);
    }

    public function testSavesAndRetrievesExchangeRate(): void
    {
        $this->repository->saveRate("USD", "ZAR", 18.50);
        $rate = $this->repository->getRate("USD", "ZAR");

        $this->assertSame(18.50, $rate);
    }

    public function testReturnsOneForIdenticalCurrencies(): void
    {
        $rate = $this->repository->getRate("ZAR", "ZAR");
        $this->assertSame(1.0, $rate);
    }

    public function testThrowsExceptionWhenRateIsNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("No exchange rate found for pair EUR/ZAR.");

        $this->repository->getRate("EUR", "ZAR");
    }
}
