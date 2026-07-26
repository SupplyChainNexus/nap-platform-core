<?php

declare(strict_types=1);

namespace NAP\Application\Services;

final class AudatexReconciliationService
{
    private AudatexClaimEvaluator $evaluator;

    public function __construct(AudatexClaimEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    /**
     * Reconciles a repairer's preferred vendor invoice against the original Audatex estimate.
     *
     * @param string $oemPartNumber
     * @param float $audatexAuthorisedCap
     * @param string $audatexAssignedSupplier
     * @param string $repairerPreferredSupplier
     * @param float $preferredSupplierActualPrice
     * @return array<string, mixed>
     */
    public function reconcilePreferredPurchase(
        string $oemPartNumber,
        float $audatexAuthorisedCap,
        string $audatexAssignedSupplier,
        string $repairerPreferredSupplier,
        float $preferredSupplierActualPrice
    ): array {
        $priceDifference = round($preferredSupplierActualPrice - $audatexAuthorisedCap, 2);
        $isCompliantWithInsurance = $preferredSupplierActualPrice <= $audatexAuthorisedCap;

        $marginStatus = 'MATCHED_WITHIN_CAP';
        if ($priceDifference > 0) {
            $marginStatus = 'OVER_CAP_REPAIRER_MARGIN_LEAK';
        } elseif ($priceDifference < 0) {
            $marginStatus = 'UNDER_CAP_REPAIRER_PROFIT_SAVING';
        }

        return [
            'oemPartNumber'               => strtoupper(trim($oemPartNumber)),
            'audatexAssignedSupplier'     => trim($audatexAssignedSupplier),
            'audatexAuthorisedCap'        => $audatexAuthorisedCap,
            'repairerPreferredSupplier'   => trim($repairerPreferredSupplier),
            'preferredSupplierActualPrice'=> $preferredSupplierActualPrice,
            'priceVariance'               => $priceDifference,
            'isInsuranceApproved'         => $isCompliantWithInsurance,
            'reconciliationStatus'        => $marginStatus,
            'auditNote'                   => $isCompliantWithInsurance
                ? sprintf("Invoice from %s (R %.2f) is within Audatex cap (R %.2f). Approved for job closure.", $repairerPreferredSupplier, $preferredSupplierActualPrice, $audatexAuthorisedCap)
                : sprintf("CRITICAL: %s quoted R %.2f, exceeding Audatex cap of R %.2f by R %.2f. Requires price match adjustment.", $repairerPreferredSupplier, $preferredSupplierActualPrice, $audatexAuthorisedCap, $priceDifference)
        ];
    }
}
