<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Catalogue;

use NAP\Domain\Services\PartsCatalogueInterface;

final class InMemoryPartsCatalogue implements PartsCatalogueInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $catalogue = [];

    public function __construct()
    {
        $this->seedDefaults();
    }

    private function seedDefaults(): void
    {
        $this->catalogue["NISSAN|NP200|FRONT BUMPER COVER"] = [
            "oemPartNumber" => "F2022-1DA0A",
            "description" => "FRONT BUMPER COVER",
            "category" => "BODY_FRONT",
            "benchmarkPriceExclVat" => 4250.00
        ];

        $this->catalogue["NISSAN|NP200|HEADLAMP ASSY RH"] = [
            "oemPartNumber" => "26010-1DA0A",
            "description" => "HEADLAMP ASSY RH",
            "category" => "LIGHTING_FRONT",
            "benchmarkPriceExclVat" => 3800.00
        ];

        $this->catalogue["NISSAN|NP200|FRONT FENDER RH"] = [
            "oemPartNumber" => "F3100-1DA0A",
            "description" => "FRONT FENDER RH",
            "category" => "BODY_FRONT",
            "benchmarkPriceExclVat" => 2950.00
        ];
    }

    /**
     * @param string $make
     * @param string $model
     * @param string $description
     * @param string $guideNumber
     * @return array<string, mixed>|null
     */
    public function findOemPart(string $make, string $model, string $description, string $guideNumber = ""): ?array
    {
        $normalizedMake = strtoupper(trim($make));
        $modelParts = explode("/", trim($model));
        $normalizedModel = strtoupper($modelParts[0] ?? $model);
        $normalizedDesc = strtoupper(trim($description));

        $key = "{$normalizedMake}|{$normalizedModel}|{$normalizedDesc}";

        return $this->catalogue[$key] ?? null;
    }
}
