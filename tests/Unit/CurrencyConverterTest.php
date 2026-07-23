<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Contracts\ExchangeRateProviderInterface;
use NAP\Application\Services\CurrencyConverter;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;
use PHPUnit\Framework\TestCase;

final class CurrencyConverterTest extends TestCase
{
    public function testConvertsUsdToZarCorrectly(): void
    {
        $mockProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $mockProvider->expects($this->once())
            ->method("getRate")
            ->with("USD", "ZAR")
            ->willReturn(18.50);

        $converter = new CurrencyConverter($mockProvider);
        $usdMoney = NAPMoney::fromCents(1000, "USD"); // $10.00 USD

        $zarMoney = $converter->convert($usdMoney, "ZAR");

        $this->assertSame(18500, $zarMoney->getAmountInCents()); // R185.00 ZAR
        $this->assertSame("ZAR", $zarMoney->getCurrency());
    }

    public function testReturnsSameInstanceIfTargetCurrencyIsIdentical(): void
    {
        $mockProvider = $this->createMock(ExchangeRateProviderInterface::class);
        $mockProvider->expects($this->never())->method("getRate");

        $converter = new CurrencyConverter($mockProvider);
        $zarMoney = NAPMoney::fromCents(5000, "ZAR");

        $result = $converter->convert($zarMoney, "ZAR");

        $this->assertSame($zarMoney, $result);
    }
}
