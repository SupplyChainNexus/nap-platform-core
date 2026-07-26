<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;

final class AudatexClaimEvaluator
{
    private PartCrossReferenceRepository $repository;

    public function __construct(PartCrossReferenceRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Evaluates an Audatex claim item against supplier quotes and authorized price caps.
     *
     * @param string $oemPartNumber
     * @param float $authorizedPrice
     * @param string|null $preferredSupplier
     * @return array{
     *     oemPartNumber: string,
     *     authorizedPrice: float,
     *     preferredSupplier: string|null,
     *     status: string,
     *     compliantOptionsCount: int,
     *     overCapCount: int,
     *     bestCompliantOption: array<string, mixed>|null,
     *     allAlternatives: array<int, array<string, mixed>>
     * }
     */
    public function evaluateClaimItem(
        string $oemPartNumber,
        float $authorizedPrice,
        ?string $preferredSupplier = null
    ): array {
        $alternatives = $this->repository->findAlternativesForOem($oemPartNumber);

        $compliantOptions = [];
        $overCapOptions = [];
        $bestCompliantOption = null;

        foreach ($alternatives as $alt) {
            $price = (float) ($alt['lastQuotedPrice'] ?? 0.0);
            $brand = (string) ($alt['brandName'] ?? '');
            
            $isCompliant = $price <= $authorizedPrice;
            $isPreferred = $preferredSupplier !== null && stripos($brand, $preferredSupplier) !== false;

            $enrichedOption = array_merge($alt, [
                'isCompliantWithCap' => $isCompliant,
                'isPreferredSupplier' => $isPreferred,
                'priceVariance' => round($price - $authorizedPrice, 2)
            ]);

            if ($isCompliant) {
                $compliantOptions[] = $enrichedOption;
                if ($bestCompliantOption === null || ($isPreferred && !($bestCompliantOption['isPreferredSupplier'] ?? false))) {
                    $bestCompliantOption = $enrichedOption;
                }
            } else {
                $overCapOptions[] = $enrichedOption;
            }
        }

        $overallStatus = 'NO_MATCHES_FOUND';
        if (count($compliantOptions) > 0) {
            $overallStatus = 'COMPLIANT_MATCH_AVAILABLE';
        } elseif (count($overCapOptions) > 0) {
            $overallStatus = 'OVER_AUTHORISED_PRICE_RISK';
        }

        return [
            'oemPartNumber'         => strtoupper(trim($oemPartNumber)),
            'authorizedPrice'       => $authorizedPrice,
            'preferredSupplier'     => $preferredSupplier,
            'status'                => $overallStatus,
            'compliantOptionsCount' => count($compliantOptions),
            'overCapCount'          => count($overCapOptions),
            'bestCompliantOption'   => $bestCompliantOption,
            'allAlternatives'       => array_merge($compliantOptions, $overCapOptions)
        ];
    }
}
