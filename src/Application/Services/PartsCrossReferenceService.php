<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Domain\Services\PartsCatalogueInterface;
use NAP\Infrastructure\Catalogue\InMemoryPartsCatalogue;

final class PartsCrossReferenceService
{
    private PartsCatalogueInterface $catalogue;

    public function __construct(?PartsCatalogueInterface $catalogue = null)
    {
        $this->catalogue = $catalogue ?? new InMemoryPartsCatalogue();
    }

    /**
     * @param string $make
     * @param string $model
     * @param array<int, array<string, mixed>> $rawParts
     * @return array<int, array<string, mixed>>
     */
    public function normalizePartsList(string $make, string $model, array $rawParts): array
    {
        $normalizedList = [];

        foreach ($rawParts as $part) {
            $desc = is_string($part["description"] ?? null) ? $part["description"] : "";
            $guideNum = is_string($part["guideNumber"] ?? null) ? $part["guideNumber"] : "";
            $givenPartNum = is_string($part["partNumber"] ?? null) ? $part["partNumber"] : "";
            $price = is_numeric($part["priceExclVat"] ?? null) ? (float) $part["priceExclVat"] : 0.0;

            $oemMatch = $this->catalogue->findOemPart($make, $model, $desc, $guideNum);

            $resolvedPartNumber = $givenPartNum;
            $confidence = 0.80;
            $matched = false;

            if ($oemMatch !== null) {
                $resolvedPartNumber = is_string($oemMatch["oemPartNumber"] ?? null) ? $oemMatch["oemPartNumber"] : $givenPartNum;
                $confidence = 1.0;
                $matched = true;
            }

            $normalizedList[] = [
                "guideNumber" => $guideNum,
                "partNumber" => $resolvedPartNumber,
                "originalPartNumber" => $givenPartNum,
                "description" => $desc,
                "priceExclVat" => $price,
                "matched" => $matched,
                "confidence" => $confidence
            ];
        }

        return $normalizedList;
    }
}
