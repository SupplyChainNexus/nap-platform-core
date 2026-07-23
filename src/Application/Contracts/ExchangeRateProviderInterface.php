<?php

declare(strict_types=1);

namespace NAP\Application\Contracts;

interface ExchangeRateProviderInterface
{
    public function getRate(string $fromCurrency, string $toCurrency): float;
}
