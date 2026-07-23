<?php
declare(strict_types=1);

namespace NAP\Tests\Unit;

use InvalidArgumentException;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;
use PHPUnit\Framework\TestCase;

final class NAPMoneyTest extends TestCase
{
    public function testCanCreateMoneyAndGetValues(): void
    {
        $money = NAPMoney::fromCents(15050, 'ZAR');

        $this->assertSame(15050, $money->getAmountInCents());
        $this->assertSame(150.5, $money->getFormattedAmount());
        $this->assertSame('ZAR', $money->getCurrency());
    }

    public function testCannotCreateNegativeMoney(): void
    {
        $this->expectException(InvalidArgumentException::class);
        NAPMoney::fromCents(-100);
    }

    public function testCanAddMoneyOfSameCurrency(): void
    {
        $m1 = NAPMoney::fromCents(1000);
        $m2 = NAPMoney::fromCents(2500);

        $result = $m1->add($m2);

        $this->assertSame(3500, $result->getAmountInCents());
    }
}