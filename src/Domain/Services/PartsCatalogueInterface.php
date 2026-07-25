<?php

declare(strict_types=1);

namespace NAP\Domain\Services;

interface PartsCatalogueInterface
{
    /**
     * @param string $make
     * @param string $model
     * @param string $description
     * @param string $guideNumber
     * @return array<string, mixed>|null
     */
    public function findOemPart(string $make, string $model, string $description, string $guideNumber = ""): ?array;
}
