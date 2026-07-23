<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http\Middleware;

final readonly class ApiKeyMiddleware
{
    /**
     * @param list<string> $validKeys
     */
    public function __construct(
        private array $validKeys
    ) {}

    /**
     * @param array<string, mixed> $headers
     * @return array{status_code: int, body: array{status: string, error: string}}|null
     */
    public function process(array $headers): ?array
    {
        $authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? null;

        if (!is_string($authHeader) || !str_starts_with($authHeader, "Bearer ")) {
            return [
                "status_code" => 401,
                "body" => [
                    "status" => "error",
                    "error" => "Unauthorized: Missing or malformed Authorization Bearer header.",
                ],
            ];
        }

        $providedKey = trim(substr($authHeader, 7));

        if (!in_array($providedKey, $this->validKeys, true)) {
            return [
                "status_code" => 401,
                "body" => [
                    "status" => "error",
                    "error" => "Unauthorized: Invalid API key provided.",
                ],
            ];
        }

        return null; // Passed authentication
    }
}
