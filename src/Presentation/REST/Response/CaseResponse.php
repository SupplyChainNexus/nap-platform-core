<?php

declare(strict_types=1);

namespace NAP\Presentation\REST\Response;

use NAP\Domain\Case\NXCase;

final readonly class CaseResponse
{
    public function __construct(
        public string $id,
        public string $businessContext,
        public string $status,
        public string $createdAt
    ) {}

    public static function fromEntity(NXCase $case): self
    {
        return new self(
            id: $case->id()->value(),
            businessContext: $case->businessContext(),
            status: $case->status()->value,
            createdAt: $case->createdAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'businessContext' => $this->businessContext,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
        ];
    }
}