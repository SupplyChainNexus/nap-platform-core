<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Commands\IngestAudatexClaimHandler;
use NAP\Application\Services\CatalogScraperAgent;
use NAP\Application\Services\PartLookupService;
use NAP\Infrastructure\Cache\ArrayCacheDriver;
use NAP\Infrastructure\Health\HealthCheckRegistry;
use NAP\Infrastructure\Http\Controllers\CachedDashboardController;
use NAP\Infrastructure\Http\Controllers\ClaimIngestController;
use NAP\Infrastructure\Http\Controllers\DashboardController;
use NAP\Infrastructure\Http\Controllers\HealthController;
use NAP\Infrastructure\Http\Controllers\PartCrossReferenceController;
use NAP\Infrastructure\Http\JsonResponse;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;
use NAP\Infrastructure\Security\IdempotencyGuard;

$pdo = new PDO("sqlite:" . __DIR__ . "/../database.sqlite");
$db = new DatabaseAdapter($pdo);
$cache = new ArrayCacheDriver();
$idempotencyGuard = new IdempotencyGuard($db);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header("Content-Type: application/json");

// Route 1: Health Probes
if ($uri === '/health/live' && $method === 'GET') {
    $controller = new HealthController(new HealthCheckRegistry($db, $cache));
    echo $controller->getLiveness();
    exit;
}

if ($uri === '/health/ready' && $method === 'GET') {
    $controller = new HealthController(new HealthCheckRegistry($db, $cache));
    echo $controller->getReadiness();
    exit;
}

// Route 2: Claim Ingestion Webhook
if ($uri === '/api/v1/claims/ingest' && $method === 'POST') {
    $rawBody = (string) file_get_contents('php://input');
    /** @var array<string, mixed> $payload */
    $payload = json_decode($rawBody, true) ?? [];
    
    $sigHeader = $_SERVER['HTTP_X_SIGNATURE_256'] ?? null;
    $idKeyHeader = $_SERVER['HTTP_X_IDEMPOTENCY_KEY'] ?? null;

    $handler = new IngestAudatexClaimHandler();
    $controller = new ClaimIngestController($handler, null, $idempotencyGuard);

    echo $controller->handleWebhook($payload, $rawBody, $sigHeader, $idKeyHeader);
    exit;
}

// Route 3: OEM Part Cross-Referencing
if ($uri === '/api/v1/parts/cross-reference' && $method === 'GET') {
    $repository = new PartCrossReferenceRepository($db);
    $lookupService = new PartLookupService($repository);
    $controller = new PartCrossReferenceController($lookupService);

    $response = $controller->handle($_GET);
    echo JsonResponse::create($response['body'], $response['status_code']);
    exit;
}

// Route 4: Scraper Worker Status & Logs Monitoring
if ($uri === '/api/v1/agent/status' && $method === 'GET') {
    $logFile = '/var/log/worker.log';
    $logs = file_exists($logFile) ? file_get_contents($logFile) : "Worker log file not initialized yet.";

    echo JsonResponse::create([
        "status" => "success",
        "worker_mode" => "embedded_background_thread",
        "recent_logs" => array_slice(explode("\n", (string) $logs), -20)
    ], 200);
    exit;
}

// Serve UI index page for GET /
if ($uri === '/' && $method === 'GET') {
    header("Content-Type: text/html");
    readfile(__DIR__ . '/index.html');
    exit;
}

http_response_code(404);
echo (string) json_encode([
    "status" => "error",
    "code" => 404,
    "message" => "Endpoint not found"
]);
