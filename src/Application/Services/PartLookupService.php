<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Domain\Model\SupplierQuote;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;

final class PartLookupService
{
    private PartCrossReferenceRepository $repository;

    public function __construct(PartCrossReferenceRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Indexes all line items from a submitted quote into the catalog store.
     */
    public function indexQuote(SupplierQuote $quote): void
    {
        foreach ($quote->getLineItems() as $item) {
            $this->repository->recordMapping(
                $item->getOemPartNumber(),
                $item->getSupplierPartNumber(),
                $item->getBrandName(),
                $item->getQuotedPrice()
            );
        }
    }

    /**
     * Discovers matching alternative part numbers for a target OEM part.
     *
     * @param string $oemPartNumber
     * @return array<int, array{supplierPartNumber: string, brandName: string, lastQuotedPrice: float, occurrenceCount: int, updatedAt: string}>
     */
    public function getAlternativeParts(string $oemPartNumber): array
    {
        return $this->repository->findAlternativesForOem($oemPartNumber);
    }
}