<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Agents;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;

final class GeminiAgentAdapter implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey = "", string $model = "gemini-3.5-flash")
    {
        $this->apiKey = trim($apiKey);
        
        $clean = str_replace("models/", "", trim($model));
        if (empty($clean) || str_contains($clean, "1.5") || str_contains($clean, "2.0") || str_contains($clean, "2.5")) {
            $clean = "gemini-3.5-flash";
        }

        $this->model = $clean;
    }

    /**
     * @param PromptContext $context
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function generateStructuredOutput(PromptContext $context, array $options = []): array
    {
        if (empty($this->apiKey)) {
            return $this->fallbackResponse($context, "Missing GEMINI_API_KEY environment variable");
        }

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

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey);

        $lastErrorMessage = "No response from Gemini API";
        $attempts = 2; // Auto-retry transient load errors

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                usleep(500000); // Wait 0.5s before retry
            }

            $ch = @curl_init($url);
            if ($ch === false) {
                continue;
            }

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_USERAGENT, "NAP-Platform/1.0");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);

            $response = @curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            @curl_close($ch);

            if ($response !== false && $httpCode === 200) {
                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode((string) $response, true);
                
                if (is_array($decoded) && isset($decoded["candidates"]) && is_array($decoded["candidates"])) {
                    /** @var array<string, mixed> $firstCandidate */
                    $firstCandidate = $decoded["candidates"][0] ?? [];
                    /** @var array<string, mixed> $content */
                    $content = $firstCandidate["content"] ?? [];
                    /** @var array<int, array<string, mixed>> $parts */
                    $parts = $content["parts"] ?? [];
                    $rawText = is_string($parts[0]["text"] ?? null) ? $parts[0]["text"] : "";

                    /** @var array<string, mixed>|null $data */
                    $data = json_decode($rawText, true);

                    if (is_array($data) && isset($data["recommendedAmount"])) {
                        return $data;
                    }
                }
            }

            if ($response !== false) {
                /** @var array<string, mixed>|null $errDecoded */
                $errDecoded = json_decode((string) $response, true);
                $errorData = is_array($errDecoded["error"] ?? null) ? $errDecoded["error"] : [];
                $errMsg = is_string($errorData["message"] ?? null) ? $errorData["message"] : "";

                if ($errMsg !== "") {
                    $lastErrorMessage = "{$this->model} ({$httpCode}): " . $errMsg;
                } else {
                    $lastErrorMessage = "{$this->model} (HTTP {$httpCode})";
                }
            }
        }

        return $this->fallbackResponse($context, $lastErrorMessage);
    }

    /**
     * @return array<string, mixed>
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