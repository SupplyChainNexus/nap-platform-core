<?php
declare(strict_types=1);

namespace NAP\SharedKernel\Domain\ValueObjects;

use InvalidArgumentException;

final readonly class NAPMoney
{
    private function __construct(
        private int $amountInCents,
        private string $currency = 'ZAR'
    ) {
        if ($this->amountInCents < 0) {
            throw new InvalidArgumentException('Monetary currency parameters cannot evaluate to a negative value.');
        }
    }

    public static function fromCents(int $amountInCents, string $currency = 'ZAR'): self
    {
        return new self($amountInCents, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getFormattedAmount(): float
    {
        return $this->amountInCents / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch error during math consolidation operations.');
        }
    }
}