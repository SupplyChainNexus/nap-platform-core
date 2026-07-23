<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use NAP\Application\Handlers\CreateCaseHandler;
use NAP\Application\Intelligence\Agents\Pricing\PricingIntelligenceAgent;
use NAP\Application\Services\CurrencyConverter;
use NAP\Infrastructure\Http\AnalyzePricingApiController;
use NAP\Infrastructure\Http\CreateCaseApiController;
use NAP\Infrastructure\Http\Middleware\ApiKeyMiddleware;
use NAP\Infrastructure\Http\Router;
use NAP\Infrastructure\Intelligence\HttpLlmProviderAdapter;
use NAP\Infrastructure\Persistence\DatabaseExchangeRateRepository;
use NAP\Infrastructure\Persistence\InMemoryCaseRepository;
use NAP\SharedKernel\Domain\Contracts\ClockInterface;
use NAP\SharedKernel\Domain\Contracts\IdGeneratorInterface;

// 1. Initialize SQLite Database & Apply Migrations
$dbPath = __DIR__ . "/../database.sqlite";
$pdo = new PDO("sqlite:" . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/** @var string $schema1 */
$schema1 = file_get_contents(__DIR__ . "/../resources/migrations/001_initial_schema.sql");
/** @var string $schema2 */
$schema2 = file_get_contents(__DIR__ . "/../resources/migrations/002_exchange_rates.sql");
$pdo->exec($schema1);
$pdo->exec($schema2);

// Seed default exchange rate
$rateRepo = new DatabaseExchangeRateRepository($pdo);
$rateRepo->saveRate("USD", "ZAR", 18.50);
$rateRepo->saveRate("EUR", "ZAR", 20.00);

// 2. Wire Dependencies
$clock = new class implements ClockInterface {
    public function now(): DateTimeImmutable {
        return new DateTimeImmutable();
    }
};

$idGen = new class implements IdGeneratorInterface {
    public function generate(): string {
        return sprintf(
            "018e38f9-%04x-7%03x-%04x-%012x",
            random_int(0, 0xffff),
            random_int(0, 0xfff),
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffffffffffff)
        );
    }
};

$caseHandler = new CreateCaseHandler($idGen, $clock, new InMemoryCaseRepository());
$createCaseController = new CreateCaseApiController($caseHandler);

$converter = new CurrencyConverter($rateRepo);
$llmAdapter = new HttpLlmProviderAdapter();
$pricingAgent = new PricingIntelligenceAgent($llmAdapter, $converter);
$analyzePricingController = new AnalyzePricingApiController($pricingAgent);

// 3. Register Middleware & Routes
$authMiddleware = new ApiKeyMiddleware(["nap-secret-key-2026"]);
$router = new Router();

$router->post("/api/cases", fn(array $payload) => $createCaseController->handle($payload));
$router->post("/api/pricing/analyze", fn(array $payload) => $analyzePricingController->handle($payload));

// 4. Capture & Process Request
$headers = getallheaders();
$authError = $authMiddleware->process($headers);

if ($authError !== null) {
    http_response_code($authError["status_code"]);
    header("Content-Type: application/json");
    echo json_encode($authError["body"]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
$uri = $_SERVER["REQUEST_URI"] ?? "/";
/** @var array<string, mixed> $payload */
$payload = json_decode((string) file_get_contents("php://input"), true) ?? [];

$response = $router->dispatch($method, $uri, $payload);

http_response_code($response["status_code"]);
header("Content-Type: application/json");
echo json_encode($response["body"]);
