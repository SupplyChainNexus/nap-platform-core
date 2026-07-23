<?php

declare(strict_types=1);

namespace NAP\Tests\Unit; // Make sure NAP\ is present!

use DateTimeImmutable;
use NAP\Domain\Case\CaseStatus;
use NAP\Domain\Case\Events\CaseSubmittedEvent;
use NAP\Domain\Case\Exceptions\InvalidCaseStatusTransitionException;
use NAP\Domain\Case\NXCase;
use NAP\SharedKernel\Domain\Identity\CaseId;
use PHPUnit\Framework\TestCase;

final class NXCaseStatusTest extends TestCase
{
    public function testCaseIsCreatedInDraftStatus(): void
    {
        $case = NXCase::create(
            new CaseId('018e38f9-472b-7b33-8a30-89196b0521e1'),
            'Test Context',
            new DateTimeImmutable()
        );

        $this->assertSame(CaseStatus::DRAFT, $case->status());
    }

    public function testCanSubmitDraftCaseAndRecordEvent(): void
    {
        $now = new DateTimeImmutable();
        $caseId = new CaseId('018e38f9-472b-7b33-8a30-89196b0521e1');
        $case = NXCase::create($caseId, 'Test Context', $now);

        $case->submit($now);

        $this->assertSame(CaseStatus::SUBMITTED, $case->status());

        $events = $case->pullDomainEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(CaseSubmittedEvent::class, $events[0]);
        $this->assertSame($caseId, $events[0]->caseId);

        // Pulling again should return empty list (cleared)
        $this->assertCount(0, $case->pullDomainEvents());
    }

    public function testCannotSubmitAlreadySubmittedCase(): void
    {
        $now = new DateTimeImmutable();
        $case = NXCase::create(
            new CaseId('018e38f9-472b-7b33-8a30-89196b0521e1'),
            'Test Context',
            $now
        );

        $case->submit($now);

        $this->expectException(InvalidCaseStatusTransitionException::class);
        $case->submit($now);
    }
}