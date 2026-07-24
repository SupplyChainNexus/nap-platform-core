<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Agents\GeminiAgentAdapter;

header('Content-Type: application/json');

// 1. Read and parse incoming HTTP JSON payload
$rawInput = file_get_contents('php://input');
$requestData = json_decode((string) $rawInput, true) ?? [];

// 2. Fall back to POST parameters if JSON body is empty
$partNumber = !empty($requestData['partNumber']) 
    ? trim((string) $requestData['partNumber']) 
    : trim((string) ($_POST['partNumber'] ?? 'NAP-SERIES-900'));

$rawAmount = $requestData['normalizedAmount'] ?? ($_POST['normalizedAmount'] ?? 85000.0);
$normalizedAmount = is_numeric($rawAmount) ? (float) $rawAmount : 85000.0;

$supplierId = !empty($requestData['supplierId']) 
    ? trim((string) $requestData['supplierId']) 
    : 'SUPPLIER-001';

// 3. Construct PromptContext with real, dynamic user variables
$context = new PromptContext('procurement_evaluation', [
    'partNumber' => $partNumber,
    'normalizedAmount' => $normalizedAmount,
    'supplierId' => $supplierId
]);

// 4. Instantiate Adapter with Environment Configuration
$apiKey = getenv('GEMINI_API_KEY') ?: '';
$model = getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash';

$agent = new GeminiAgentAdapter($apiKey, $model);

// 5. Execute AI Recommendation
try {
    $evaluation = $agent->generateStructuredOutput($context);

    // Merge evaluated part number back into response telemetry for audit matching
    $evaluation['partNumber'] = $partNumber;
    $evaluation['originalAmount'] = (int) $normalizedAmount;
    $evaluation['currency'] = 'ZAR';

    echo json_encode([
        'status' => 'success',
        'timestamp' => date('c'),
        'evaluation' => $evaluation
    ], JSON_PRETTY_PRINT);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}