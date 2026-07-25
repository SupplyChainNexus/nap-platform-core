<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $data
     * @param int $statusCode
     * @return string
     */
    public static function create(array $data, int $statusCode = 200): string
    {
        http_response_code($statusCode);
        $encoded = json_encode([
            "status" => $statusCode >= 200 && $statusCode < 300 ? "success" : "error",
            "code" => $statusCode,
            "data" => $data
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : "{}";
    }
}
