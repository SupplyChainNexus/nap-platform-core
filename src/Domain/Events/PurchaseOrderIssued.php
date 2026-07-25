<?php

declare(strict_types=1);

namespace NAP\Domain\Events;

final class PurchaseOrderIssued extends AbstractDomainEvent
{
    /**
     * @param array{poId: string, quoteId: string, supplierId: string, caseId: string, totalAmount: float, savingsAmount: float} $payload
     */
    public function __construct(string $streamId, array $payload)
    {
        parent::__construct($streamId, $payload);
    }

    public function getEventName(): string
    {
        return "PurchaseOrderIssued";
    }
}