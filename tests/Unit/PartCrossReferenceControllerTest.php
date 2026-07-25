<?php

declare(strict_types=1);

namespace NAP\Tests\Unit;

use NAP\Application\Services\PartLookupService;
use NAP\Domain\Model\QuoteLineItem;
use NAP\Domain\Model\SupplierQuote;
use NAP\Infrastructure\Http\Controllers\PartCrossReferenceController;
use NAP\Infrastructure\Http\Router;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;
use PHPUnit\Framework\TestCase;

final class PartCrossReferenceControllerTest extends TestCase
{
    public function testPartCrossReferenceEndpointReturnsMatchingParts(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);

        $repo = new PartCrossReferenceRepository($db);
        $repo->initializeSchema();
        $service = new PartLookupService($repo);

        $item = new QuoteLineItem("A2058800100", "GMK-205-FB", "Grandmark", 1200.00, 1800.00);
        $quote = new SupplierQuote("Q-100", "SUP-001", "CASE-801", [$item]);
        $service->indexQuote($quote);

        $controller = new PartCrossReferenceController($service);

        $router = new Router();
        $router->get("/api/v1/parts/cross-reference", fn (array $params) => $controller->handle($params));

        $response = $router->dispatch("GET", "/api/v1/parts/cross-reference?oem=A2058800100");

        $this->assertEquals(200, $response["status_code"]);
        $this->assertEquals("success", $response["body"]["status"]);

        /** @var array{oemPartNumber: string, matchesCount: int, alternatives: array<int, array{supplierPartNumber: string, brandName: string}>} $body */
        $body = $response["body"];
        $this->assertEquals("A2058800100", $body["oemPartNumber"]);
        $this->assertEquals(1, $body["matchesCount"]);
        $this->assertEquals("GMK-205-FB", $body["alternatives"][0]["supplierPartNumber"]);
        $this->assertEquals("Grandmark", $body["alternatives"][0]["brandName"]);
    }

    public function testPartCrossReferenceEndpointReturnsBadRequestWhenOemMissing(): void
    {
        $pdo = new \PDO("sqlite::memory:");
        $db = new DatabaseAdapter($pdo);
        $repo = new PartCrossReferenceRepository($db);
        $repo->initializeSchema();
        $service = new PartLookupService($repo);

        $controller = new PartCrossReferenceController($service);

        $router = new Router();
        $router->get("/api/v1/parts/cross-reference", fn (array $params) => $controller->handle($params));

        $response = $router->dispatch("GET", "/api/v1/parts/cross-reference");

        $this->assertEquals(400, $response["status_code"]);
        $this->assertEquals("error", $response["body"]["status"]);
    }
}
