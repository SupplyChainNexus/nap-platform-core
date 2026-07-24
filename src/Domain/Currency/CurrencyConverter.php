<?php

declare(strict_types=1);

namespace NAP\Domain\Currency;

final class CurrencyConverter
{
    /** @var array<string, float> */
    private array $exchangeRates = [
        "ZAR" => 1.0,
        "USD" => 18.25,
        "EUR" => 19.80,
        "GBP" => 23.40,
        "AUD" => 12.10
    ];

    public function convertToZar(float $amount, string $fromCurrency): float
    {
        $code = strtoupper(trim($fromCurrency));
        $rate = $this->exchangeRates[$code] ?? 1.0;
        return $amount * $rate;
    }

    public function convertFromZar(float $amountInZar, string $toCurrency): float
    {
        $code = strtoupper(trim($toCurrency));
        $rate = $this->exchangeRates[$code] ?? 1.0;
        return $rate > 0 ? $amountInZar / $rate : $amountInZar;
    }

    /**
     * @return array<string, float>
     */
    public function getRates(): array
    {
        return $this->exchangeRates;
    }
}
