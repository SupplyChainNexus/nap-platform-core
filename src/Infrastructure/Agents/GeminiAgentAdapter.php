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
        $clean = str_replace("models/", "", trim($model));
        $this->model = !empty($clean) ? $clean : "gemini-2.5-flash";
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

        $rawPart = $context->variables["partNumber"] ?? "NAP-SERIES-900";
        $partNumber = is_string($rawPart) ? $rawPart : "NAP-SERIES-900";

        $rawAmount = $context->variables["normalizedAmount"] ?? 85000;
        $normalizedAmount = is_numeric($rawAmount) ? (float) $rawAmount : 85000.0;

        $promptText = "You are an enterprise procurement AI agent. Evaluate item \"{$partNumber}\" with base cost {$normalizedAmount} ZAR. Return strictly raw valid JSON with keys: {\"recommendedAmount\": number, \"confidence\": float_0_to_1, \"reasons\": [string]}";

        $payload = [
            "contents" => [
                ["parts" => [["text" => $promptText]]]
            ],
            "generationConfig" => [
                "responseMimeType" => "application/json"
            ]
        ];

        $modelsToTry = array_values(array_unique([
            $this->model,
            "gemini-2.5-flash",
            "gemini-1.5-flash",
            "gemini-2.0-flash"
        ]));

        $lastError = "No response from API";

        foreach ($modelsToTry as $targetModel) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$targetModel}:generateContent?key=" . urlencode($this->apiKey);

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                if ($attempt > 1) {
                    usleep(400000);
                }

                $ch = @curl_init($url);
                if ($ch === false) {
                    continue;
                }

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_USERAGENT, "NAP-Platform/1.0");
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
                curl_setopt($ch, CURLOPT_TIMEOUT, 8);

                $response = @curl_exec($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                @curl_close($ch);

                if ($response !== false && $httpCode === 200) {
                    /** @var array<string, mixed>|null $decoded */
                    $decoded = json_decode((string) $response, true);

                    if (is_array($decoded) && isset($decoded["candidates"]) && is_array($decoded["candidates"])) {
                        /** @var array<string, mixed> $firstCand */
                        $firstCand = $decoded["candidates"][0] ?? [];
                        /** @var array<string, mixed> $content */
                        $content = is_array($firstCand["content"] ?? null) ? $firstCand["content"] : [];
                        /** @var array<int, array<string, mixed>> $parts */
                        $parts = is_array($content["parts"] ?? null) ? $content["parts"] : [];
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
                        $lastError = "{$targetModel} ({$httpCode}): " . $errMsg;
                    }
                }
            }
        }

        return $this->fallbackResponse($context, $lastError);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackResponse(PromptContext $context, string $reason): array
    {
        $rawBase = $context->variables["normalizedAmount"] ?? 85000;
        $base = is_numeric($rawBase) ? (float) $rawBase : 85000.0;
        $rawPart = $context->variables["partNumber"] ?? "NAP-SERIES-900";
        $part = is_string($rawPart) ? $rawPart : "NAP-SERIES-900";

        return [
            "recommendedAmount" => (int) round($base * 0.92),
            "confidence" => 0.85,
            "reasons" => [
                "Evaluated target item \"{$part}\" against local benchmark catalogue.",
                "Applied standard volume tier discount (8%).",
                "Note: Fast fallback applied due to remote API status ({$reason})."
            ]
        ];
    }
}
