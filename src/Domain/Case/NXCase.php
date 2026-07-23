<?php

declare(strict_types=1);

namespace NAP\Domain\Case;

use DateTimeImmutable;
use NAP\Domain\Case\Events\CaseSubmittedEvent;
use NAP\Domain\Case\Exceptions\InvalidCaseStatusTransitionException;
use NAP\SharedKernel\Domain\Identity\CaseId;

final class NXCase
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private readonly CaseId $id,
        private readonly string $businessContext,
        private CaseStatus $status,
        private readonly DateTimeImmutable $createdAt
    ) {}

    public static function create(
        CaseId $id,
        string $businessContext,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            $id,
            $businessContext,
            CaseStatus::DRAFT,
            $createdAt
        );
    }

    public function submit(DateTimeImmutable $now): void
    {
        if (!$this->status->canTransitionTo(CaseStatus::SUBMITTED)) {
            throw InvalidCaseStatusTransitionException::fromStatus($this->status, CaseStatus::SUBMITTED);
        }

        $this->status = CaseStatus::SUBMITTED;
        $this->recordEvent(new CaseSubmittedEvent($this->id, $now));
    }

    public function id(): CaseId
    {
        return $this->id;
    }

    public function status(): CaseStatus
    {
        return $this->status;
    }

    public function businessContext(): string
    {
        return $this->businessContext;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}