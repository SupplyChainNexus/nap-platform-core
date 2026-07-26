<?php

declare(strict_types=1);

namespace NAP\Application\Services;

use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;

final class CatalogScraperAgent
{
    private PartCrossReferenceRepository $repository;

    public function __construct(PartCrossReferenceRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Executes the scraping pass across target vehicle catalogs/search terms.
     *
     * @param array<int, string> $targetOemPartNumbers
     * @return array{scrapedCount: int, addedCount: int}
     */
    public function runScrapePass(array $targetOemPartNumbers = []): array
    {
        $scrapedCount = 0;
        $addedCount = 0;

        // Fallback target list spanning diverse manufacturers (Mercedes, BMW, VAG, Toyota, Ford, Nissan)
        $searchTargets = !empty($targetOemPartNumbers) ? $targetOemPartNumbers : [
            'A2058800100', // Mercedes C-Class Bumper
            '34116858652', // BMW 3-Series Brake Disc
            '5G1941005',   // VW Golf Headlight
            '16400-0L140', // Toyota Hilux Radiator
            'HU711/51x',   // MANN Oil Filter
            '26300-35505', // Hyundai/Kia Oil Filter
            'FL-820-S',    // Ford Motorcraft Filter
        ];

        foreach ($searchTargets as $oemNumber) {
            $extractedData = $this->scrapeProviderForOem($oemNumber);
            foreach ($extractedData as $item) {
                $scrapedCount++;
                $this->repository->recordMapping(
                    $item['oemPartNumber'],
                    $item['supplierPartNumber'],
                    $item['brandName'],
                    $item['price']
                );
                $addedCount++;
            }
        }

        return [
            'scrapedCount' => $scrapedCount,
            'addedCount' => $addedCount,
        ];
    }

    /**
     * Simulates DOM / API payload scraping against automotive part databases.
     * Replace or extend with cURL / Guzzle calls for live Web Crawling.
     *
     * @return array<int, array{oemPartNumber: string, supplierPartNumber: string, brandName: string, price: float}>
     */
    private function scrapeProviderForOem(string $oemPartNumber): array
    {
        $oemClean = strtoupper(trim($oemPartNumber));
        
        // Mocked crawler extraction logic for common OEM patterns
        $commonBrands = ['Brembo', 'Bosch', 'Denso', 'Hella', 'Valeo', 'Meyle', 'Febi Bilstein', 'Blic'];
        $results = [];

        for ($i = 0; $i < rand(2, 4); $i++) {
            $brand = $commonBrands[array_rand($commonBrands)];
            $partSuffix = strtoupper(substr(md5($brand . $oemClean . $i), 0, 8));
            $results[] = [
                'oemPartNumber' => $oemClean,
                'supplierPartNumber' => "ALT-" . $partSuffix,
                'brandName' => $brand,
                'price' => (float) rand(800, 4500) + (rand(0, 99) / 100),
            ];
        }

        return $results;
    }
}
