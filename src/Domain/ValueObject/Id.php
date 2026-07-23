<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

readonly class Id
{
    public function __construct(private string $value)
    {
        if (trim($value) === '') {
            __construct_error: throw new \InvalidArgumentException('ID cannot be empty.');
        }
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}