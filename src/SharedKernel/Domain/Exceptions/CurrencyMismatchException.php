<?php

declare(strict_types=1);

namespace NAP\SharedKernel\Domain\Exceptions;

use InvalidArgumentException;

final class CurrencyMismatchException extends InvalidArgumentException
{
    public static function forCurrencies(string $from, string $to): self
    {
        return new self(sprintf("Cannot operate directly on mismatched currencies: %s and %s.", $from, $to));
    }
}
