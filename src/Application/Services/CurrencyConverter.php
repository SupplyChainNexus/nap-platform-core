<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Application\Contracts\ExchangeRateProviderInterface;
use NAP\SharedKernel\Domain\ValueObjects\NAPMoney;

final readonly class CurrencyConverter
{
    public function __construct(
        private ExchangeRateProviderInterface $rateProvider
    ) {}

    public function convert(NAPMoney $money, string $targetCurrency): NAPMoney
    {
        $sourceCurrency = $money->getCurrency();

        if (strtoupper($sourceCurrency) === strtoupper($targetCurrency)) {
            return $money;
        }

        $rate = $this->rateProvider->getRate($sourceCurrency, $targetCurrency);
        $convertedCents = (int) round($money->getAmountInCents() * $rate);

        return NAPMoney::fromCents($convertedCents, $targetCurrency);
    }
}
