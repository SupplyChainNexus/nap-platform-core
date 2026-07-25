<?php

declare(strict_types=1);

namespace NAP\Infrastructure\Security;

final class HmacSignatureVerifier
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Verifies that the provided HMAC SHA-256 signature matches the raw payload.
     *
     * @param string $rawPayload
     * @param string $providedSignature
     * @return bool
     */
    public function verify(string $rawPayload, string $providedSignature): bool
    {
        if ($this->secret === "" || $providedSignature === "") {
            return false;
        }

        $computedSignature = hash_hmac("sha256", $rawPayload, $this->secret);

        return hash_equals($computedSignature, trim($providedSignature));
    }
}