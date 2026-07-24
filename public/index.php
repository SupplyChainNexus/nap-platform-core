<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/../vendor/autoload.php';

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Infrastructure\Agents\GeminiAgentAdapter;
use NAP\Infrastructure\Persistence\EvaluationRepository;

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri === '/api/evaluate') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

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
        $model = getenv('GEMINI_MODEL') ?: 'gemini-3.5-flash';

        $agent = new GeminiAgentAdapter($apiKey, $model);
        $evaluation = $agent->generateStructuredOutput($context);

        $evaluation['partNumber'] = $partNumber;
        $evaluation['originalAmount'] = (int) $normalizedAmount;
        $evaluation['currency'] = 'ZAR';

        try {
            $repo = new EvaluationRepository();
            $repo->logEvaluation($partNumber, $supplierId, $normalizedAmount, $evaluation);
        } catch (\Throwable $dbEx) {
            // Silently skip if disk write is constrained
        }

        echo json_encode([
            'status' => 'success',
            'timestamp' => date('c'),
            'evaluation' => $evaluation
        ], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'API Error: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
    exit;
}

if ($uri === '/api/history') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    try {
        $repo = new EvaluationRepository();
        $history = $repo->getRecentEvaluations(20);

        echo json_encode([
            'status' => 'success',
            'count' => count($history),
            'history' => $history
        ], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch history: ' . $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
    exit;
}

if ($uri === '/admin.html' || $uri === '/') {
    ob_end_clean();
    if (file_exists(__DIR__ . '/admin.html')) {
        require __DIR__ . '/admin.html';
    } else {
        echo "Admin console file not found.";
    }
    exit;
}

ob_end_clean();
header('Content-Type: application/json');
echo json_encode(['status' => 'error', 'message' => 'Route not found: ' . $uri]);
exit;