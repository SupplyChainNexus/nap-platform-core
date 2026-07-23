<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Persistence;

use DateTimeImmutable;
use NAP\Domain\Case\CaseStatus;
use NAP\Domain\Case\NXCase;
use NAP\Domain\Repositories\CaseRepository;
use NAP\SharedKernel\Domain\Identity\CaseId;
use PDO;
use ReflectionClass;

final readonly class PdoCaseRepository implements CaseRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function save(NXCase $case): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cases (id, business_context, status, created_at, updated_at)
             VALUES (:id, :business_context, :status, :created_at, :updated_at)
             ON CONFLICT(id) DO UPDATE SET
                status = excluded.status,
                updated_at = excluded.updated_at'
        );

        $stmt->execute([
            'id' => $case->id()->value(),
            'business_context' => $case->businessContext(),
            'status' => $case->status()->value,
            'created_at' => $case->createdAt()->format(DATE_ATOM),
            'updated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }

    public function load(CaseId $caseId): ?NXCase
    {
        $stmt = $this->pdo->prepare('SELECT * FROM cases WHERE id = :id');
        $stmt->execute(['id' => $caseId->value()]);

        /** @var array{id: string, business_context: string, status: string, created_at: string}|false $row */
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $case = NXCase::create(
            id: new CaseId($row['id']),
            businessContext: $row['business_context'],
            createdAt: new DateTimeImmutable($row['created_at'])
        );

        if ($row['status'] === CaseStatus::SUBMITTED->value) {
            $reflection = new ReflectionClass($case);
            $statusProperty = $reflection->getProperty('status');
            $statusProperty->setValue($case, CaseStatus::SUBMITTED);
        }

        return $case;
    }
}