<?php

declare(strict_types=1);

namespace NAP\Presentation\REST\Request;

use InvalidArgumentException;

final readonly class CreateCaseRequest
{
    public function __construct(
        public string $businessContext
    ) {
        if (trim($this->businessContext) === '') {
            throw new InvalidArgumentException('Business context cannot be empty.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        if (!isset($payload['businessContext']) || !is_string($payload['businessContext'])) {
            throw new InvalidArgumentException('Missing or invalid "businessContext" string in request body.');
        }

        return new self($payload['businessContext']);
    }
}