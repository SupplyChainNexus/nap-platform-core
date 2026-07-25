<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Security;

final class JwtAuthenticator
{
    private string $jwtSecret;
    private string $expectedIssuer;

    public function __construct(string $jwtSecret, string $expectedIssuer = "nap-platform-auth")
    {
        $this->jwtSecret = $jwtSecret;
        $this->expectedIssuer = $expectedIssuer;
    }

    /**
     * Generates a signed JWT token for testing/authentication.
     *
     * @param string $userId
     * @param array<string, mixed> $extraClaims
     * @param int $ttlSeconds
     * @return string
     */
    public function generateToken(string $userId, array $extraClaims = [], int $ttlSeconds = 3600): string
    {
        $header = ["alg" => "HS256", "typ" => "JWT"];
        $now = time();
        $payload = array_merge([
            "iss" => $this->expectedIssuer,
            "sub" => $userId,
            "iat" => $now,
            "exp" => $now + $ttlSeconds
        ], $extraClaims);

        $base64UrlHeader = $this->base64UrlEncode(json_encode($header) ?: "");
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload) ?: "");

        $signature = hash_hmac("sha256", "$base64UrlHeader.$base64UrlPayload", $this->jwtSecret, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    /**
     * Validates a JWT token string. Returns decoded payload if valid, null otherwise.
     *
     * @param string $jwtToken
     * @return array<string, mixed>|null
     */
    public function authenticate(string $jwtToken): ?array
    {
        $parts = explode(".", trim($jwtToken));
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $sigB64] = $parts;

        $expectedSig = $this->base64UrlEncode(
            hash_hmac("sha256", "$headerB64.$payloadB64", $this->jwtSecret, true)
        );

        if (!hash_equals($expectedSig, $sigB64)) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            return null;
        }

        $iss = is_string($payload["iss"] ?? null) ? $payload["iss"] : "";
        $exp = is_numeric($payload["exp"] ?? null) ? (int) $payload["exp"] : 0;

        if ($iss !== $this->expectedIssuer || time() >= $exp) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat("=", 4 - $remainder);
        }
        return base64_decode(strtr($data, "-_", "+/"));
    }
}