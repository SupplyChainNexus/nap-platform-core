<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Security\Auth;

final class JwtManager
{
    private string $secret;

    public function __construct(string $secret = "nap_enterprise_jwt_secret_key_2026")
    {
        $this->secret = $secret;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function generateToken(array $payload, int $ttlSeconds = 3600): string
    {
        $header = json_encode(["alg" => "HS256", "typ" => "JWT"]);
        $payload["exp"] = time() + $ttlSeconds;
        $payload["iat"] = time();

        $base64Header = $this->base64UrlEncode((string) $header);
        $base64Payload = $this->base64UrlEncode((string) json_encode($payload));

        $signature = hash_hmac("sha256", "{$base64Header}.{$base64Payload}", $this->secret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeToken(string $token): ?array
    {
        $parts = explode(".", $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $signature = hash_hmac("sha256", "{$base64Header}.{$base64Payload}", $this->secret, true);
        if (!hash_equals($this->base64UrlEncode($signature), $base64Signature)) {
            return null;
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($this->base64UrlDecode($base64Payload), true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = is_numeric($payload["exp"] ?? null) ? (int) $payload["exp"] : 0;
        if (time() > $exp) {
            return null; // Token Expired
        }

        return $payload;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, "-_", "+/") . str_repeat("=", (4 - strlen($data) % 4) % 4));
    }
}
