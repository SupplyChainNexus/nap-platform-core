<?php

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use NAP\Application\Orchestration\OrchestrationEngine;
use NAP\Domain\Currency\CurrencyConverter;
use NAP\Infrastructure\Integrations\ErpPayloadAdapter;
use NAP\Infrastructure\Persistence\DatabaseAdapter;
use NAP\Infrastructure\Security\ApiSecurity;
use NAP\Infrastructure\Security\Auth\RateLimiter;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

// 1. Enforce Rate Limiting (Phase 7)
$rateLimiter = new RateLimiter(60, 60);
$clientIp = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";
if (!$rateLimiter->isAllowed($clientIp)) {
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Rate limit exceeded. Maximum 60 requests per minute."]);
    exit;
}

// 2. Enforce API Security Middleware (Phase 3 & 7)
$security = new ApiSecurity();
if (!$security->authorizeRequest()) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Invalid or missing Authorization Bearer token."]);
    exit;
}

$requestUri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
$requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";

// Initialize Subsystems
$dbAdapter = new DatabaseAdapter();
$pdo = $dbAdapter->getPdo();
$currencyConverter = new CurrencyConverter();
$erpAdapter = new ErpPayloadAdapter();
$orchestrationEngine = new OrchestrationEngine();

// ROUTE: POST /api/evaluate (Handles Standard, ERP Webhook & Multi-Currency)
if ($requestUri === "/api/evaluate" && $requestMethod === "POST") {
    $rawInput = file_get_contents("php://input");
    /** @var array<string, mixed>|null $input */
    $input = json_decode((string) $rawInput, true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON payload body"]);
        exit;
    }

    // Ingest payload via ERP Adapter (Phase 6)
    $parsed = $erpAdapter->parseErpPayload($input);
    $incomingCurrency = $parsed["currency"];
    $amountInOriginalCurrency = $parsed["normalizedAmount"];

    // Convert foreign currencies (USD, EUR, GBP) to base ZAR for evaluation (Phase 6)
    $amountInZar = $currencyConverter->convertToZar($amountInOriginalCurrency, $incomingCurrency);

    // Evaluate via Multi-Agent Engine
    $evaluation = $orchestrationEngine->evaluatePurchaseOrder(
        $parsed["partNumber"],
        $amountInZar,
        $parsed["supplierId"]
    );

    // Persist Telemetry in Database (Phase 5: PostgreSQL / SQLite)
    try {
        $stmt = $pdo->prepare("INSERT INTO evaluation_logs (
            part_number, supplier_id, original_amount, recommended_amount, savings_amount, confidence, currency, decision, reasons, created_at
        ) VALUES (:part, :supplier, :orig, :rec, :sav, :conf, :curr, :dec, :reasons, :created)");

        $stmt->execute([
            ":part" => $evaluation["partNumber"],
            ":supplier" => $evaluation["supplierId"],
            ":orig" => $evaluation["originalAmount"],
            ":rec" => $evaluation["recommendedAmount"],
            ":sav" => $evaluation["savingsAmount"],
            ":conf" => $evaluation["confidence"],
            ":curr" => $incomingCurrency,
            ":dec" => $evaluation["decision"],
            ":reasons" => json_encode($evaluation["reasons"]),
            ":created" => date("Y-m-d H:i:s")
        ]);
    } catch (\Throwable $e) {
        // Fallback log catch
    }

    echo json_encode([
        "status" => "success",
        "timestamp" => date("c"),
        "databaseDriver" => $dbAdapter->getDriver(),
        "inputCurrency" => $incomingCurrency,
        "evaluation" => $evaluation
    ], JSON_PRETTY_PRINT);
    exit;
}

// ROUTE: GET /api/history (Persistent Telemetry History)
if ($requestUri === "/api/history" && $requestMethod === "GET") {
    try {
        $stmt = $pdo->query("SELECT * FROM evaluation_logs ORDER BY id DESC LIMIT 20");
        $rows = $stmt->fetchAll();

        $history = array_map(function($row) {
            /** @var array<string, mixed> $row */
            $reasonsRaw = is_string($row["reasons"] ?? null) ? $row["reasons"] : "[]";
            /** @var array<int, string> $reasonsDecoded */
            $reasonsDecoded = json_decode($reasonsRaw, true) ?: [];

            return [
                "id" => $row["id"],
                "part_number" => $row["part_number"],
                "supplier_id" => $row["supplier_id"],
                "original_amount" => $row["original_amount"],
                "recommended_amount" => $row["recommended_amount"],
                "savings_amount" => $row["savings_amount"],
                "confidence" => $row["confidence"],
                "currency" => $row["currency"] ?? "ZAR",
                "decision" => $row["decision"],
                "reasons" => $reasonsDecoded,
                "created_at" => $row["created_at"]
            ];
        }, $rows);

        echo json_encode(["status" => "success", "driver" => $dbAdapter->getDriver(), "history" => $history], JSON_PRETTY_PRINT);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Fallback Default Route
echo json_encode([
    "status" => "online",
    "engine" => "NAP Platform Core v2.0 Enterprise",
    "security" => "JWT & Rate Limiter Active",
    "database" => $dbAdapter->getDriver(),
    "phpVersion" => PHP_VERSION
]);

