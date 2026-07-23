<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Agents;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;

final class OpenAiAgentAdapter implements LlmProviderInterface
{
    private string $apiKey;
    private string $model;

    public function __construct(?string $apiKey = null, string $model = "gpt-4o-mini")
    {
        $this->apiKey = $apiKey ?? (string) (getenv("OPENAI_API_KEY") ?: "");
        $this->model = $model;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function generateStructuredOutput(PromptContext $context, array $options = []): array
    {
        $variables = $context->variables;

        if (empty($this->apiKey)) {
            $rawAmount = $variables["normalizedAmount"] ?? 10000;
            $amount = is_int($rawAmount) || is_float($rawAmount) ? (int) $rawAmount : 10000;

            return [
                "recommendedAmount" => (int) round($amount * 0.95),
                "confidence" => 0.85,
                "reasons" => [
                    "[Fallback Rule] Applied default 5% dynamic volume discount (No OpenAI Key provided)."
                ]
            ];
        }

        $prompt = [
            [
                "role" => "system",
                "content" => "You are an enterprise procurement pricing optimization agent. Return ONLY valid raw JSON with keys: recommendedAmount (integer cents), confidence (float 0-1), and reasons (array of strings)."
            ],
            [
                "role" => "user",
                "content" => (string) json_encode($variables)
            ]
        ];

        try {
            $ch = curl_init("https://api.openai.com/v1/chat/completions");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "Authorization: Bearer " . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode([
                "model" => $this->model,
                "messages" => $prompt,
                "temperature" => 0.2,
                "response_format" => ["type" => "json_object"]
            ]));

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !is_string($response)) {
                throw new \RuntimeException("OpenAI API returned HTTP code " . $httpCode);
            }

            /** @var array{choices?: array<int, array{message?: array{content?: string}}>} $decoded */
            $decoded = (array) json_decode($response, true);
            $contentRaw = $decoded["choices"][0]["message"]["content"] ?? "{}";
            
            /** @var array{recommendedAmount?: mixed, confidence?: mixed, reasons?: mixed} $content */
            $content = (array) json_decode($contentRaw, true);

            $rawDefaultAmount = $variables["normalizedAmount"] ?? 10000;
            $defaultAmount = is_int($rawDefaultAmount) || is_float($rawDefaultAmount) ? (int) $rawDefaultAmount : 10000;

            $recommendedAmount = isset($content["recommendedAmount"]) && (is_int($content["recommendedAmount"]) || is_float($content["recommendedAmount"]))
                ? (int) $content["recommendedAmount"]
                : $defaultAmount;

            $confidence = isset($content["confidence"]) && (is_int($content["confidence"]) || is_float($content["confidence"]))
                ? (float) $content["confidence"]
                : 0.90;

            $reasons = isset($content["reasons"]) && is_array($content["reasons"])
                ? array_values(array_map("strval", $content["reasons"]))
                : ["OpenAI " . $this->model . " dynamic pricing analysis applied."];

            return [
                "recommendedAmount" => $recommendedAmount,
                "confidence" => $confidence,
                "reasons" => $reasons
            ];
        } catch (\Throwable $e) {
            $rawDefaultAmount = $variables["normalizedAmount"] ?? 10000;
            $defaultAmount = is_int($rawDefaultAmount) || is_float($rawDefaultAmount) ? (int) $rawDefaultAmount : 10000;

            return [
                "recommendedAmount" => $defaultAmount,
                "confidence" => 0.50,
                "reasons" => [
                    "[Circuit Breaker Fallback] LLM request failed: " . $e->getMessage()
                ]
            ];
        }
    }
}
