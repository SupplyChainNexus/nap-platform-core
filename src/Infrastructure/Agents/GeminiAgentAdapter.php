<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Agents;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;

final class GeminiAgentAdapter implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey = "", string $model = "gemini-1.5-flash")
    {
        $this->apiKey = trim($apiKey);
        $this->model = !empty($model) ? $model : "gemini-1.5-flash";
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

        $activeModel = $this->discoverActiveModel();

        $url = "https://generativelanguage.googleapis.com/v1beta/{$activeModel}:generateContent?key=" . urlencode($this->apiKey);

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

        return $this->fallbackResponse($context, "Gemini API HTTP Error {$httpCode} on model {$activeModel}");
    }

    /**
     * Query Google API for active generateContent models
     */
    private function discoverActiveModel(): string
    {
        $listUrl = "https://generativelanguage.googleapis.com/v1beta/models?key=" . urlencode($this->apiKey);
        
        $ch = @curl_init($listUrl);
        if ($ch === false) {
            return "models/gemini-1.5-flash";
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = @curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        if ($response !== false && $httpCode === 200) {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode((string) $response, true);

            if (is_array($decoded) && isset($decoded["models"]) && is_array($decoded["models"])) {
                /** @var array<int, array<string, mixed>> $modelList */
                $modelList = $decoded["models"];

                foreach ($modelList as $m) {
                    $methods = is_array($m["supportedGenerationMethods"] ?? null) ? $m["supportedGenerationMethods"] : [];
                    $name = is_string($m["name"] ?? null) ? $m["name"] : "";

                    if ($name !== "" && in_array("generateContent", $methods, true)) {
                        return $name;
                    }
                }
            }
        }

        return str_starts_with($this->model, "models/") ? $this->model : "models/" . $this->model;
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
