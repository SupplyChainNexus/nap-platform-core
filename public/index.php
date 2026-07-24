<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Agents\GeminiAgentAdapter;

// Route API requests cleanly
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/api/evaluate') {
    header('Content-Type: application/json');

    try {
        $rawInput = file_get_contents('php://input');
        $data = json_decode((string) $rawInput, true) ?? [];

        $partNumber = !empty($data['partNumber']) ? trim((string) $data['partNumber']) : 'NAP-SERIES-900';
        $normalizedAmount = isset($data['normalizedAmount']) ? (float) $data['normalizedAmount'] : 85000.0;
        $supplierId = !empty($data['supplierId']) ? trim((string) $data['supplierId']) : 'SUPPLIER-001';

        $context = new PromptContext('procurement_evaluation', [
            'partNumber' => $partNumber,
            'normalizedAmount' => $normalizedAmount,
            'supplierId' => $supplierId
        ]);

        $apiKey = getenv('GEMINI_API_KEY') ?: '';
        $model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';

        $agent = new GeminiAgentAdapter($apiKey, $model);
        $evaluation = $agent->generateStructuredOutput($context);

        $evaluation['partNumber'] = $partNumber;
        $evaluation['originalAmount'] = (int) $normalizedAmount;
        $evaluation['currency'] = 'ZAR';

        echo json_encode([
            'status' => 'success',
            'timestamp' => date('c'),
            'evaluation' => $evaluation
        ], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        http_response_code(200); // Return valid JSON on errors to prevent UI crashes
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
    exit;
}

// Serve static UI if requested directly
if ($uri === '/admin.html' || $uri === '/') {
    require __DIR__ . '/admin.html';
    exit;
}