<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Security;

final class ApiSecurity
{
    private string $expectedApiKey;

    public function __construct(?string $expectedApiKey = null)
    {
        $this->expectedApiKey = $expectedApiKey ?? (getenv("NAP_API_KEY") ?: "nap_live_sec_key_2026");
    }

    public function authorizeRequest(): bool
    {
        // Allow public access for browser sessions originating from admin.html console
        $referer = $_SERVER["HTTP_REFERER"] ?? "";
        if (!empty($referer) && str_contains($referer, "admin.html")) {
            return true;
        }

        // Check Authorization Header (Bearer nap_live_sec_key_2026)
        $headers = function_exists("getallheaders") ? getallheaders() : [];
        $authHeader = $headers["Authorization"] ?? ($headers["authorization"] ?? ($_SERVER["HTTP_AUTHORIZATION"] ?? ""));

        if (!empty($authHeader) && preg_match("/Bearer\s+(.*)$/i", $authHeader, $matches)) {
            return hash_equals($this->expectedApiKey, trim($matches[1]));
        }

        // Check Query Parameter fallback (?api_key=...)
        $queryKey = $_GET["api_key"] ?? "";
        if (is_string($queryKey) && !empty($queryKey)) {
            return hash_equals($this->expectedApiKey, trim($queryKey));
        }

        return false;
    }
}
