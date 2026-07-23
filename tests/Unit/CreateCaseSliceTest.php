<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Domain\Case\CaseStatus;
use NAP\Domain\Case\NXCase;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\Infrastructure\Services\SystemClock;
use NAP\Infrastructure\Services\UuidGenerator;
use NAP\SharedKernel\Domain\Identity\CaseId;
use PHPUnit\Framework\TestCase;

final class CreateCaseSliceTest extends TestCase
{
    public function testCanCreateAndPersistProcurementCase(): void
    {
        $idGenerator = new UuidGenerator();
        $clock = new SystemClock();
        $repository = new InMemoryCaseRepository();

        $rawUuid = $idGenerator->generate();
        $caseId = new CaseId($rawUuid);

        $case = NXCase::create($caseId, 'retail', $clock->now());

        $repository->save($case);

        $savedCase = $repository->find($caseId);

        $this->assertNotNull($savedCase);
        $this->assertSame($rawUuid, $savedCase->id()->value());
        $this->assertSame('retail', $savedCase->businessContext());
        $this->assertSame(CaseStatus::DRAFT, $savedCase->status());
    }
}