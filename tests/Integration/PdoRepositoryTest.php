<?php

declare(strict_types=1);

namespace NAP\Tests\Integration;

use DateTimeImmutable;
use NAP\Domain\Audit\AuditLog;
use NAP\Domain\Case\CaseStatus;
use NAP\Domain\Case\NXCase;
use NAP\Infrastructure\Persistence\PdoAuditLogRepository;
use NAP\Infrastructure\Persistence\PdoCaseRepository;
use NAP\SharedKernel\Domain\Identity\CaseId;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoCaseRepository $caseRepository;
    private PdoAuditLogRepository $auditLogRepository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        /** @var string $schema */
        $schema = file_get_contents(__DIR__ . '/../../resources/migrations/001_initial_schema.sql');
        $this->pdo->exec($schema);

        $this->caseRepository = new PdoCaseRepository($this->pdo);
        $this->auditLogRepository = new PdoAuditLogRepository($this->pdo);
    }

    public function testCaseCanBePersistedAndRetrievedFromDatabase(): void
    {
        $caseId = new CaseId('018e38f9-472b-7b33-8a30-89196b0521e1');
        $case = NXCase::create($caseId, 'Automotive Supply Chain', new DateTimeImmutable());

        $this->caseRepository->save($case);
        $retrievedCase = $this->caseRepository->load($caseId);

        $this->assertNotNull($retrievedCase);
        $this->assertSame('018e38f9-472b-7b33-8a30-89196b0521e1', $retrievedCase->id()->value());
        $this->assertSame('Automotive Supply Chain', $retrievedCase->businessContext());
        $this->assertSame(CaseStatus::DRAFT, $retrievedCase->status());
    }

    public function testAuditLogCanBePersistedAndRetrieved(): void
    {
        $aggregateId = '018e38f9-472b-7b33-8a30-89196b0521e1';
        $log = new AuditLog(
            id: '018e38f9-472b-7b33-8a30-89196b0521e2',
            aggregateId: $aggregateId,
            action: 'CASE_SUBMITTED',
            occurredAt: new DateTimeImmutable(),
            payload: ['status' => 'SUBMITTED']
        );

        $this->auditLogRepository->save($log);
        $logs = $this->auditLogRepository->findByAggregateId($aggregateId);

        $this->assertCount(1, $logs);
        $this->assertSame('CASE_SUBMITTED', $logs[0]->action);
        $this->assertSame(['status' => 'SUBMITTED'], $logs[0]->payload);
    }
}