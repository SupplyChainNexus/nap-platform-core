<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use NAP\Application\Intelligence\Agents\PricingIntelligenceAgent;
use NAP\Infrastructure\Agents\GeminiAgentAdapter;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);

// Public Admin & Telemetry routes
if ($uri === "/admin.html" || $uri === "/admin") {
    if (file_exists(__DIR__ . "/admin.html")) {
        header("Content-Type: text/html");
        echo file_get_contents(__DIR__ . "/admin.html");
        exit;
    }
}

if ($uri === "/api/v1/telemetry") {
    header("Content-Type: application/json");
    echo json_encode([
        "status" => "ok",
        "timestamp" => date("c"),
        "activeAgents" => [
            "PricingIntelligenceAgent",
            "HistoricalAuditAgent",
            "SupplierReputationAgent"
        ],
        "outboxQueueLength" => 0,
        "memoryUsageMB" => round(memory_get_usage() / 1024 / 1024, 2)
    ]);
    exit;
}

// Authenticated API Routes
$headers = getallheaders();
$authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? "";

if (!str_starts_with($authHeader, "Bearer ")) {
    http_response_code(401);
    header("Content-Type: application/json");
    echo json_encode([
        "status" => "error",
        "error" => "Unauthorized: Missing or malformed Authorization Bearer header."
    ]);
    exit;
}

if ($uri === "/api/v1/analyze-pricing" && $_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    
    $rawInput = file_get_contents("php://input");
    /** @var array{partNumber?: string, amount?: float|int, supplierId?: string, currency?: string} $data */
    $data = json_decode($rawInput ?: "{}", true);

    $geminiKey = getenv("GEMINI_API_KEY") ?: "";
    $adapter = new GeminiAgentAdapter(apiKey: $geminiKey);
    $agent = new PricingIntelligenceAgent($adapter);

    $amount = (float) ($data["amount"] ?? 10000);
    $evaluation = $agent->evaluate(["normalizedAmount" => $amount]);

    echo json_encode([
        "status" => "success",
        "timestamp" => date("c"),
        "evaluation" => [
            "partNumber" => $data["partNumber"] ?? "NAP-UNKNOWN",
            "originalAmount" => $amount,
            "recommendedAmount" => $evaluation["recommendedAmount"],
            "confidence" => $evaluation["confidence"],
            "reasons" => $evaluation["reasons"],
            "currency" => $data["currency"] ?? "ZAR"
        ]
    ]);
    exit;
}

http_response_code(404);
header("Content-Type: application/json");
echo json_encode(["status" => "error", "error" => "Route not found"]);

