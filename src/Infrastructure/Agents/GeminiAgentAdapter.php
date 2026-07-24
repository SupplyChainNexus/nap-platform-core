<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Agents;

use NAP\Application\Intelligence\Prompting\PromptContext;
use NAP\Domain\Intelligence\LlmProviderInterface;

final class GeminiAgentAdapter implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey = "", string $model = "gemini-1.5-flash")
    {
        $this->apiKey = trim($apiKey);
        $this->model = $model;
    }

    /**
     * @return array{recommendedAmount: int|float, confidence: float, reasons: array<int, string>}
     */
    public function generateStructuredOutput(PromptContext $context): array
    {
        if (empty($this->apiKey)) {
            return $this->fallbackResponse($context, "Missing GEMINI_API_KEY environment variable");
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";

        $promptText = "Analyze this procurement payload for context template {$context->templateName} with variables: " 
            . json_encode($context->variables) 
            . ". Respond strictly with raw valid JSON containing: {\"recommendedAmount\": number, \"confidence\": float_0_to_1, \"reasons\": [string]}";

        $payload = [
            "contents" => [
                ["parts" => [["text" => $promptText]]]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json"
            ]
        ];

        $ch = @curl_init($url);
        if ($ch === false) {
            return $this->fallbackResponse($context, "Unable to initialize cURL");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "x-goog-api-key: " . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);

        $response = @curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return $this->fallbackResponse($context, "Gemini API HTTP Error {$httpCode}");
        }

        /** @var array{candidates?: array<int, array{content?: array{parts?: array<int, array{text?: string}>}}>} $decoded */
        $decoded = json_decode((string) $response, true);
        $rawText = $decoded["candidates"][0]["content"]["parts"][0]["text"] ?? "";

        /** @var array{recommendedAmount?: mixed, confidence?: mixed, reasons?: mixed}|null $data */
        $data = json_decode($rawText, true);

        if (!is_array($data) || !isset($data["recommendedAmount"])) {
            return $this->fallbackResponse($context, "Invalid JSON structure from Gemini response");
        }

        $recAmount = is_numeric($data["recommendedAmount"]) ? (float) $data["recommendedAmount"] : 0.0;
        $conf = is_numeric($data["confidence"] ?? null) ? (float) $data["confidence"] : 0.88;
        
        /** @var array<int, string> $reasons */
        $reasons = is_array($data["reasons"] ?? null) ? $data["reasons"] : ["Evaluated via Google Gemini Agent"];

        return [
            "recommendedAmount" => $recAmount,
            "confidence" => $conf,
            "reasons" => $reasons
        ];
    }

    /**
     * @return array{recommendedAmount: int|float, confidence: float, reasons: array<int, string>}
     */
    private function fallbackResponse(PromptContext $context, string $reason): array
    {
        $rawBase = $context->variables["normalizedAmount"] ?? 10000;
        $base = is_numeric($rawBase) ? (float) $rawBase : 10000.0;

        return [
            "recommendedAmount" => (int) round($base * 0.90),
            "confidence" => 0.60,
            "reasons" => ["Circuit Breaker Fallback: {$reason}"]
        ];
    }
}
