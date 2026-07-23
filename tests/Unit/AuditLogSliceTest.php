<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Commands\SubmitCaseCommand;
use NAP\Application\Handlers\SubmitCaseHandler;
use NAP\Application\Listeners\RecordCaseSubmittedAuditLog;
use NAP\Domain\Case\Events\CaseSubmittedEvent;
use NAP\Domain\Case\NXCase;
use NAP\Infrastructure\Messaging\InMemoryEventDispatcher;
use NAP\Infrastructure\Persistence\InMemoryAuditLogRepository;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\Infrastructure\Services\SystemClock;
use NAP\Infrastructure\Services\UuidGenerator;
use NAP\SharedKernel\Domain\Identity\CaseId;
use PHPUnit\Framework\TestCase;

final class AuditLogSliceTest extends TestCase
{
    public function testSubmittingCaseCreatesAuditLogEntry(): void
    {
        // Setup Infrastructure
        $caseRepository = new InMemoryCaseRepository();
        $auditRepository = new InMemoryAuditLogRepository();
        $clock = new SystemClock();
        $idGenerator = new UuidGenerator();
        $dispatcher = new InMemoryEventDispatcher();

        // Register Listener
        $listener = new RecordCaseSubmittedAuditLog($auditRepository, $idGenerator);
        $dispatcher->listen(
            CaseSubmittedEvent::class,
            static function (object $event) use ($listener): void {
                if ($event instanceof CaseSubmittedEvent) {
                    $listener($event);
                }
            }
        );

        // Seed Draft Case
        $caseId = new CaseId('018e38f9-472b-7b33-8a30-89196b0521e1');
        $case = NXCase::create($caseId, 'Procurement', $clock->now());
        $caseRepository->save($case);

        // Submit Case
        $handler = new SubmitCaseHandler($caseRepository, $clock, $dispatcher);
        $handler->handle(new SubmitCaseCommand($caseId));

        // Verify Audit Log was generated
        $logs = $auditRepository->findByAggregateId($caseId->value());

        $this->assertCount(1, $logs);
        $this->assertSame($caseId->value(), $logs[0]->aggregateId);
        $this->assertSame('CASE_SUBMITTED', $logs[0]->action);
    }
}