<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Agents;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;

final class GeminiAgentAdapter implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey = "", string $model = "gemini-2.5-flash")
    {
        $this->apiKey = trim($apiKey);
        $this->model = !empty($model) ? $model : "gemini-2.5-flash";
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

        // Try primary model and active fallback models across v1 and v1beta API versions
        $candidateModels = array_unique([
            $this->model,
            "gemini-2.5-flash",
            "gemini-2.0-flash",
            "gemini-1.5-flash"
        ]);

        $apiVersions = ["v1", "v1beta"];

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

        $lastError = "Unknown error";

        foreach ($apiVersions as $version) {
            foreach ($candidateModels as $candidate) {
                $cleanModel = str_starts_with($candidate, "models/") ? substr($candidate, 7) : $candidate;
                $url = "https://generativelanguage.googleapis.com/{$version}/models/{$cleanModel}:generateContent?key=" . urlencode($this->apiKey);

                $ch = @curl_init($url);
                if ($ch === false) {
                    continue;
                }

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                $response = @curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                @curl_close($ch);

                if ($response !== false && $httpCode === 200) {
                    /** @var array{candidates?: array<int, array{content?: array{parts?: array<int, array{text?: string}>}}>} $decoded */
                    $decoded = json_decode((string) $response, true);
                    $rawText = $decoded["candidates"][0]["content"]["parts"][0]["text"] ?? "";

                    /** @var array<string, mixed>|null $data */
                    $data = json_decode($rawText, true);

                    if (is_array($data) && isset($data["recommendedAmount"])) {
                        return $data;
                    }
                }

                $lastError = "Gemini API HTTP Error {$httpCode}";
            }
        }

        return $this->fallbackResponse($context, $lastError);
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
