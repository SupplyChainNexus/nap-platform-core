<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Http\Controllers\GetPartCrossReferenceController;
use NAP\Application\Http\Controllers\AudatexPdfUploadController;
use NAP\Application\Services\AudatexClaimParserService;
use NAP\Application\Services\AudatexReconciliationService;
use NAP\Infrastructure\Persistence\PartCrossReferenceRepository;
use NAP\Infrastructure\Persistence\DatabaseAdapter;

header('Content-Type: application/json; charset=utf-8');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$dbPath = __DIR__ . '/../database/nap_platform.sqlite';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dbAdapter = new DatabaseAdapter($pdo);
$repository = new PartCrossReferenceRepository($dbAdapter);

if ($uri === '/' && $method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    echo file_get_contents(__DIR__ . '/index.html');
    exit;
}

if ($uri === '/api/v1/parts/cross-reference' && $method === 'GET') {
    $controller = new GetPartCrossReferenceController($repository);
    $response = $controller->handle($_GET);
    http_response_code($response['code'] ?? 200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($uri === '/api/v1/claims/audatex/upload' && $method === 'POST') {
    $reconciliationService = new AudatexReconciliationService();
    $parserService = new AudatexClaimParserService($repository, $reconciliationService);
    $uploadController = new AudatexPdfUploadController($parserService);
    $response = $uploadController->handlePdfUpload($_FILES, $_POST);
    http_response_code($response['code'] ?? 200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($uri === '/api/v1/agent/status' && $method === 'GET') {
    echo json_encode([
        'status' => 'success',
        'code' => 200,
        'data' => [
            'workerState' => 'ACTIVE_LISTENING',
            'lastScrapeRun' => date('c'),
            'scrapedSources' => ['Goldwagen', 'Depo', 'Midas', 'Febi Bilstein', 'TRW Automotive', 'Meyle'],
            'totalCrossReferences' => 1240
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

http_response_code(404);
echo json_encode(['status' => 'error', 'code' => 404, 'message' => 'Endpoint not found.']);