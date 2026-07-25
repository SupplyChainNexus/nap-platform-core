<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Connectors;

use NAP\Domain\Contracts\SupplierConnectorInterface;
use NAP\Domain\ValueObjects\PurchaseOrder;

final class SupplierConnectorRegistry
{
    /** @var array<string, SupplierConnectorInterface> */
    private array $connectors = [];

    /**
     * @param array<int, SupplierConnectorInterface> $initialConnectors
     */
    public function __construct(array $initialConnectors = [])
    {
        foreach ($initialConnectors as $connector) {
            $this->register($connector);
        }
    }

    public function register(SupplierConnectorInterface $connector): void
    {
        $this->connectors[$connector->getSupplierName()] = $connector;
    }

    /**
     * Dispatches RFQs to all registered suppliers.
     *
     * @param string $caseId
     * @param array<int, array<string, mixed>> $parts
     * @return array<string, array<string, mixed>>
     */
    public function broadcastRfq(string $caseId, array $parts): array
    {
        $results = [];

        foreach ($this->connectors as $name => $connector) {
            $results[$name] = $connector->requestQuotes($caseId, $parts);
        }

        return $results;
    }

    /**
     * Dispatches a Purchase Order to the designated supplier.
     *
     * @param PurchaseOrder $purchaseOrder
     * @return bool
     */
    public function dispatchPo(PurchaseOrder $purchaseOrder): bool
    {
        $supplierName = $purchaseOrder->getSupplierName();

        if (isset($this->connectors[$supplierName])) {
            return $this->connectors[$supplierName]->dispatchPurchaseOrder($purchaseOrder);
        }

        foreach ($this->connectors as $connector) {
            if ($connector->dispatchPurchaseOrder($purchaseOrder)) {
                return true;
            }
        }

        return false;
    }

    public function getConnectorCount(): int
    {
        return count($this->connectors);
    }
}