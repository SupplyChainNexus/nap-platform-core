<?php

declare(strict_types=1);

namespace NAP\Application\Listeners;

use DateTimeImmutable;
use NAP\Domain\Audit\AuditLog;
use NAP\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use NAP\Domain\Case\Events\CaseSubmittedEvent;

final readonly class RecordCaseSubmittedAuditLog
{
    public function __construct(
        private AuditLogRepositoryInterface $auditLogRepository
    ) {}

    public function __invoke(CaseSubmittedEvent $event): void
    {
        $this->handle($event);
    }

    public function handle(CaseSubmittedEvent $event): void
    {
        $log = new AuditLog(
            id: 'audit-' . $event->caseId->value(),
            aggregateId: $event->caseId->value(),
            action: 'CASE_SUBMITTED',
            occurredAt: $event->occurredAt ?? new DateTimeImmutable(),
            payload: [
                'caseId' => $event->caseId->value(),
                'status' => 'SUBMITTED',
            ]
        );

        $this->auditLogRepository->save($log);
    }
}