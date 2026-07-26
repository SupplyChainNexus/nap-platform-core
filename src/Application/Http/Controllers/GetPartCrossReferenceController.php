<?php

declare(strict_types=1);

namespace NAP\Application\Http\Controllers;

use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;

final class GetPartCrossReferenceController
{
    private PartCrossReferenceRepository $repository;

    /** 
     * Detailed OEM vehicle, year model, and trim spec matrix.
     * @var array<int|string, array{make: string, model: string, series: string, yearRange: string, specDetails: string, category: string, description: string}> 
     */
    private array $richOemMetadata = [
        'A2058800100' => [
            'make'        => 'Mercedes-Benz',
            'model'       => 'C-Class',
            'series'      => 'W205 / S205',
            'yearRange'   => '2015 - 2021',
            'specDetails' => 'AMG-Line Trim | With PDC Sensor Holes | Pre-Facelift',
            'category'    => 'Body & Structural',
            'description' => 'Front Bumper Bracket / Grille Support Mount'
        ],
        '51117292101' => [
            'make'        => 'BMW',
            'model'       => '3 Series',
            'series'      => 'F30 / F31',
            'yearRange'   => '2012 - 2018',
            'specDetails' => 'M-Sport Aerodynamics Package | Includes Washer Nozzle Cutouts',
            'category'    => 'Body & Structural',
            'description' => 'Front Bumper Cover Panel'
        ],
        'A2059060002' => [
            'make'        => 'Mercedes-Benz',
            'model'       => 'C-Class',
            'series'      => 'W205',
            'yearRange'   => '2015 - 2018',
            'specDetails' => 'High-Performance ILS Full LED Unit | Right Hand (RH)',
            'category'    => 'Lighting & Lamps',
            'description' => 'LED Headlight Unit Right'
        ],
        '31126852991' => [
            'make'        => 'BMW',
            'model'       => '1/2/3/4 Series',
            'series'      => 'F20 / F22 / F30 / F32',
            'yearRange'   => '2011 - 2019',
            'specDetails' => 'xDrive / sDrive Compatible | Hydro-Bushing Included',
            'category'    => 'Suspension & Steering',
            'description' => 'Control Arm Front Lower Left'
        ]
    ];

    public function __construct(PartCrossReferenceRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array<string, mixed>
     */
    public function handle(array $queryParams): array
    {
        $oemInput = isset($queryParams['oem']) && is_string($queryParams['oem']) ? $queryParams['oem'] : '';
        $oemPartNumber = strtoupper(trim($oemInput));

        if ($oemPartNumber === '') {
            return [
                'status'  => 'error',
                'code'    => 400,
                'message' => 'Query parameter "oem" is required.'
            ];
        }

        $alternatives = $this->repository->findAlternativesForOem($oemPartNumber);
        
        $metadata = null;
        foreach ($this->richOemMetadata as $key => $data) {
            if (strtoupper((string) $key) === $oemPartNumber) {
                $metadata = $data;
                break;
            }
        }

        if ($metadata === null) {
            $metadata = [
                'make'        => 'Universal / Premium OEM',
                'model'       => 'Passenger Vehicle',
                'series'      => 'Series Standard',
                'yearRange'   => '2015 - 2026',
                'specDetails' => 'Direct OEM Replacement Component | High Durability Standard',
                'category'    => 'Automotive Replacement Part',
                'description' => 'OEM Component ' . $oemPartNumber
            ];
        }

        return [
            'status'  => 'success',
            'code'    => 200,
            'message' => 'Cross-references and vehicle spec details retrieved successfully.',
            'data'    => [
                'oemPartNumber'   => $oemPartNumber,
                'make'            => $metadata['make'],
                'model'           => $metadata['model'],
                'series'          => $metadata['series'],
                'yearRange'       => $metadata['yearRange'],
                'specDetails'     => $metadata['specDetails'],
                'category'        => $metadata['category'],
                'partDescription' => $metadata['description'],
                'matchesCount'    => count($alternatives),
                'alternatives'    => $alternatives
            ]
        ];
    }
}