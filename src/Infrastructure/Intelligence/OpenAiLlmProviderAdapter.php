<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Intelligence;

use NAP\Application\Intelligence\Contracts\LlmProviderInterface;
use NAP\Application\Intelligence\Prompting\PromptContext;
use RuntimeException;

final readonly class OpenAiLlmProviderAdapter implements LlmProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = "gpt-4o-mini"
    ) {}

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function generateStructuredOutput(PromptContext $context, array $options = []): array
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException("OpenAI API Key is missing.");
        }

        $promptText = (string) json_encode($context);

        $payload = [
            "model" => $this->model,
            "messages" => [
                ["role" => "system", "content" => "You are an enterprise procurement audit AI. Return structured JSON."],
                ["role" => "user", "content" => $promptText],
            ],
            "response_format" => [
                "type" => "json_object"
            ],
        ];

        $ch = curl_init("https://api.openai.com/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, (string) json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->apiKey,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new RuntimeException(sprintf("OpenAI API call failed with status %d: %s", $httpCode, (string) $response));
        }

        /** @var array{choices: list<array{message: array{content: string}}>} $decoded */
        $decoded = (array) json_decode((string) $response, true);
        $content = $decoded["choices"][0]["message"]["content"] ?? "{}";

        /** @var array<string, mixed> $json */
        $json = (array) json_decode($content, true);
        return $json;
    }
}
